#!/usr/bin/perl

use strict;
use warnings;

use DBI;
use Data::Dumper;
use Net::Ping;
use POSIX qw(strftime);
use threads;

our $running = 1;
our $update_interval = 300; # 5 minutes
our %settings;

sub signal_handler
{
    # signal our main loop that it's time to quit
    print "Exiting!\n";
    $::running = 0;
}

sub get_setting
{
    my($setting_name) = @_;
    $::dbh->{ AutoCommit } = 1;
    # select our stored procedure for hosts to scan
    my $sth = $::dbh->prepare( 'BEGIN; SELECT * FROM get_setting_value( ? );' );
    $sth->bind_param( 1, $setting_name );
    $sth->execute();

    # fetch our reference cursor
    my $cursor = $sth->fetch;
    $sth = $::dbh->prepare( 'FETCH ALL IN "'.@{ $cursor }[ 0 ].'";' );
    $sth->execute();

    # get all objects to scan
    my $array_dump = $sth->fetchall_arrayref();
    my $retval = @{ @{ $array_dump }[ 0 ] }[ 0 ];
    
    # cleanup database transaction
    $sth = $::dbh->prepare( 'COMMIT;' );
    $sth->execute();
    $sth->finish();
    return $retval;
}

sub send_email
{
    my($email_address,$fullname,$reenable_key,$host_ip,$host_description,$host_last_seen = @_;
    my $smtp = Net::SMTP->new( $::settings{ 'email_server_hostname' },
                              Port => $::settings{ 'email_server_port' });
    $smtp->mail('target-email');
    $smtp->to('target-email');
    $smtp->data();
    $smtp->datasend("data here, insert url to re-enable host");
    $smtp->dataend();
    $smtp->quit();
}

# setup our signal handler
$SIG{ INT } = \&signal_handler;

# connect
our $dbh = DBI->connect("DBI:Pg:dbname=odin;host=localhost", "dbaodin", "gresen", {'RaiseError' => 1});

$::settings{ 'email_notification_enabled' } = get_setting( 'email_notification' );
$::settings{ 'email_notification_type' } = get_setting( 'email_notification_type' );
$::settings{ 'email_server_hostname' } = get_setting( 'email_hostname' );
$::settings{ 'email_server_port' } = get_setting( 'email_port' );

while ( $::running ) {
}

# clean up
$::dbh->disconnect();
