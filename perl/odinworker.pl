#!/usr/bin/perl

#  Odin - IP plan management and tracker
#  Copyright (C) 2015-2016  Tobias Eliasson <arnestig@gmail.com>
#                           Jonas Berglund <jonas.jberglund@gmail.com>
#                           Martin Rydin <martin.rydin@gmail.com>
#
#  This program is free software; you can redistribute it and/or modify
#  it under the terms of the GNU General Public License as published by
#  the Free Software Foundation; either version 2 of the License, or
#  (at your option) any later version.
#
#  This program is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#  GNU General Public License for more details.
#
#  You should have received a copy of the GNU General Public License along
#  with this program; if not, write to the Free Software Foundation, Inc.,
#  51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.

# This script will handle scanning of hosts and emailing notifications to users.

use strict;
use warnings;

use DBI;
use HTTP::Date qw(str2time);
use Data::Dumper;
use Net::Ping;
use Scalar::Util qw(looks_like_number);
use POSIX qw(strftime);
use Net::SMTP;
use threads;
use threads::shared;

our $running = 1;
our $log_mutex;
share($::running);
share($::log_mutex);
my @mThreadPool;

$| = 1;

sub signal_handler
{
    # signal our main loop that it's time to quit
    print "Exiting!\n";
    $::running = 0;
}

# function for converting seconds to days, hours, minutes, seconds
sub seconds_to_dhms 
{
    my ($secs) = @_;
    return sprintf( "%dd, %dh, %dm, %ds\n", reverse ($secs % 60, ($secs /= 60) % 60, ($secs /= 60) % 24, int ($secs / 24)));
}

# function for returning a setting based on setting name
sub get_setting
{
    my($setting_name, $dbh) = @_;
    my $setting_value;
    # select our get_setting_value, TODO: implement non-token SP
    my $sth = $dbh->prepare( 'SELECT * FROM get_setting_value( ?, ? );' );
    $sth->bind_param( 1, '' );
    $sth->bind_param( 2, $setting_name );
    $sth->execute();
    $sth->bind_col(1,\$setting_value);
    $sth->fetch();
    return $setting_value;
}

sub notification_sender
{
    # connect to our database
    my $dbh = DBI->connect("DBI:Pg:dbname=odin;host=localhost", "dbaodin", "gresen", {'RaiseError' => 1});

    my %settings;

    while ( $::running ) {
        # collect all settings needed
        $settings{ 'email_notification_enabled' } = get_setting( 'email_notification', $dbh );
        $settings{ 'email_notification_type' } = get_setting( 'email_notification_type', $dbh );
        $settings{ 'email_server_hostname' } = get_setting( 'email_hostname', $dbh );
        $settings{ 'email_server_port' } = get_setting( 'email_port', $dbh );
        $settings{ 'email_sender' } = get_setting( 'email_sender', $dbh );
        $settings{ 'email_server_timeout' } = get_setting( 'email_server_timeout', $dbh );
        if ( $settings{ 'email_notification_enabled' } ) {
            $dbh->{ AutoCommit } = 1;

            # select our stored procedure for emails to send
            my $sth = $dbh->prepare( 'BEGIN; SELECT * FROM get_emails_to_send();' );
            $sth->execute();

            # fetch our reference cursor
            my $cursor = $sth->fetch;
            $sth = $dbh->prepare( 'FETCH ALL IN "'.@{ $cursor }[ 0 ].'";' );
            $sth->execute();

            my @mails_sent;
            # get all objects to scan
            my $nu_hashref = $sth->fetchall_hashref( 'nu_id' );
            foreach my $nu_id ( keys %{ $nu_hashref } ) {
                my $mail_item = $nu_hashref->{ $nu_id };

                my $smtp = Net::SMTP->new( $settings{ email_server_hostname }.':'.$settings{ email_server_port },
                    Timeout => $settings{ email_server_timeout } ) or olog( "Mailer", "ERROR! ".$!. ". Check your notification settings." );
                $smtp->mail( $settings{ email_sender } );
                $smtp->to( $mail_item->{ usr_email } );
                $smtp->data();
                $smtp->datasend( $mail_item->{ nu_message } );
                $smtp->dataend();
                $smtp->quit();
                olog( "Mailer", "Mail notification sent to ".$mail_item->{ usr_firstn }." ".$mail_item->{ usr_lastn }." <".$mail_item->{ usr_email }."> (".$mail_item->{ usr_usern }.")");
                push( @mails_sent, $nu_id );
            }

            # cleanup database transaction
            $sth = $dbh->prepare( 'COMMIT;' );
            $sth->execute();
            $sth->finish();

            $dbh->{ AutoCommit } = 0;

            # update notification table, set all emails as sent
            for( @mails_sent ) {
                my $sth = $dbh->prepare( 'SELECT remove_notifyuser_message( ? )' );
                $sth->bind_param( 1, $_ );
                $sth->execute();
                $sth->finish();
            }
            $dbh->commit();
        }
        # sleep 5 until next time we're checking the hosts
        sleep( 5 );
    }
    # clean up
    $dbh->disconnect();
}

# thread function for sending emails to users
sub send_email
{
    my($email_address,$fullname,$reenable_key,$host_ip,$host_description,$host_last_seen) = @_;
    while ( $::running ) {
        sleep( 5 );
    }
}

# function for scanning a host
sub scan_host_slave
{
    my($ip_address) = @_;

    my %retval;
    $retval{ ip_address } = $ip_address;
    $retval{ online } = 0;
    $retval{ proto } = "";
    $retval{ timestamp } = strftime( "%F %T", localtime ); # %Y-%m-%d %H:%M:%S
    for my $proto ( qw{ tcp icmp } ) {
        if ( Net::Ping->new( $proto, 5 )->ping($ip_address) ) {
            $retval{ online } = 1;
            $retval{ proto } = $proto;
            last;
        }
    }
    return \%retval;
}

sub olog
{
    my($origin, $logline) = @_;
    lock($::log_mutex);
    my $timestamp = strftime( "%F %T", localtime ); # %Y-%m-%d %H:%M:%S
    open( LOGFILE, ">>odin.log" );
    print LOGFILE "$timestamp - $origin - $logline\n";
    close( LOGFILE );

}

# master for the scan_host_slave threads
sub scan_host_master
{
    # connect to our database
    my $dbh = DBI->connect("DBI:Pg:dbname=odin;host=localhost", "dbaodin", "gresen", {'RaiseError' => 1});

    my %settings;

    while ( $::running ) {
        # check if we should begin scanning for hosts
        $settings{ 'host_scan_interval' } = get_setting( 'host_scan_interval', $dbh );

        my %hostinfo;
        my @hosts_to_scan;
        $dbh->{ AutoCommit } = 1;

        # select our stored procedure for hosts to scan
        my $sth = $dbh->prepare( 'BEGIN; SELECT * FROM get_hosts_to_scan();' );
        $sth->execute();

        # fetch our reference cursor
        my $cursor = $sth->fetch;
        $sth = $dbh->prepare( 'FETCH ALL IN "'.@{ $cursor }[ 0 ].'";' );
        $sth->execute();

        # get all objects to scan
        my $array_dump = $sth->fetchall_arrayref();
        foreach ( @{ $array_dump } ) {
            push( @hosts_to_scan, @{ $_ }[ 0 ] );
        }

        # cleanup database transaction
        $sth = $dbh->prepare( 'COMMIT;' );
        $sth->execute();
        $sth->finish();

        $dbh->{ AutoCommit } = 0;

        my $batch_start_time = str2time( localtime() );
        # iterate over all hosts to scan, 20 at a time
        while ( @hosts_to_scan and $::running ) {
            my @hosts_to_add;
            my @thread_pool;

            # only 20 at time, rest will be done next batch
            if ( $#hosts_to_scan >= 19 ) {
                @hosts_to_add = splice( @hosts_to_scan, -20 );
            } else {
                @hosts_to_add = splice( @hosts_to_scan, -$#hosts_to_scan-1 );
            }

            # start thread for each ping
            foreach my $target_ip ( @hosts_to_add ) {
                my $thread = threads->new(\&scan_host_slave, $target_ip );
                olog( "Scanner", "Pushed $target_ip for scanning" );
                push( @thread_pool, $thread );
            }

            # join the threads and collect result
            foreach ( @thread_pool ) {
                my %retval = %{ $_->join() };
                olog( "Scanner", "Received status on $retval{ ip_address } ($retval{ online },$retval{ proto })" );
                $hostinfo{ $retval{ ip_address } }{ online } = $retval{ online };
                $hostinfo{ $retval{ ip_address } }{ timestamp } = $retval{ timestamp };
            }

            # update database with current status of the hosts
            foreach ( keys %hostinfo ) {
                my $sth = $dbh->prepare( 'SELECT update_host_status( ?, ?, ? )' );
                $sth->bind_param( 1, $_ );
                $sth->bind_param( 2, $hostinfo{ $_ }{ online } );
                $sth->bind_param( 3, $hostinfo{ $_ }{ timestamp } );
                $sth->execute();
                $sth->finish();
            }

            $dbh->commit();
        }

        my $nItems = scalar( keys %hostinfo );
        if ( $nItems > 0 ) {
            my $elapsed = seconds_to_dhms( str2time( localtime() ) - $batch_start_time );
            olog( "Scanner", "Batch job with $nItems hosts took $elapsed" );
        }

        # sleep until next time we're checking the hosts
        for ( 1..60 ) {
            if ( $::running ) {
                sleep( 1 );
            }
        }
    }
    # clean up
    $dbh->disconnect();
}

# setup our signal handler
$SIG{ INT } = \&signal_handler;

# create our two main threads for scanning hosts and sending emails
push( @mThreadPool, threads->new(\&scan_host_master), threads->new(\&notification_sender) );

while( $::running ) {
    sleep 1;
}

for ( @mThreadPool ) {
    $_->join();
}

exit;

