/*
   Odin - IP plan management and tracker
   Copyright (C) 2015-2017  Tobias Eliasson <arnestig@gmail.com>
                            Jonas Berglund <jonas.jberglund@gmail.com>
                            Martin Rydin <martin.rydin@gmail.com>

   This program is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; either version 2 of the License, or
   (at your option) any later version.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License along
   with this program; if not, write to the Free Software Foundation, Inc.,
   51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.

*/

\c odin;

create sequence sq_networks_id maxvalue 32700 start with 1;
alter sequence sq_networks_id owner to dbaodin;

create table networks (
        nw_id smallint primary key default nextval('sq_networks_id'),
        nw_base varchar(45) not null,
        nw_cidr numeric(2) not null,
        nw_description varchar(2000) not null
        );
alter table networks owner to dbaodin;

create table hosts (
        host_ip varchar(36) primary key,
        usr_id smallint not null references users default 0,
        nw_id smallint not null references networks,
        host_name varchar(255),
        host_data varchar(45),
        host_description varchar(2000),
        host_leased timestamp null,
        host_lease_expiry timestamp null,
        host_last_seen timestamp null,
        host_last_scanned timestamp null,
        host_last_notified timestamp null,
        next_scan_prioritized boolean default false,
        token_usr smallint not null references users default 0,
        token_timestamp timestamp null
        );
alter table hosts owner to dbaodin;

-- 'Network' stored procedures
--
-- add_network
create or replace function add_network(
    ticket varchar(255),
    network_base varchar(45),
    cidr numeric(2),
    network_description varchar(2000),
    OUT status boolean,
    OUT errmsg varchar)
returns record as $$
declare
    new_nw_id smallint;
    network_cidr inet;
begin
    status = true;
    network_cidr = (network_base || '/' || cidr)::inet;
    insert into networks( nw_base, nw_cidr, nw_description ) SELECT host(network(network_cidr))::varchar, cidr, network_description;
    select into new_nw_id currval('sq_networks_id');  
    insert into hosts( host_ip, nw_id, usr_id, token_usr ) SELECT *, new_nw_id, 0, 0 FROM get_hosts_in_network( inet( network_cidr ) );
    EXCEPTION WHEN unique_violation THEN
        RAISE NOTICE 'Hosts already allocated in another network';
        errmsg = 'Hosts already allocated in another network';
        status = false;
end;
$$ language plpgsql;
alter function add_network(varchar,varchar,numeric,varchar) owner to dbaodin;

-- remove_network
create or replace function remove_network(
    ticket varchar(255),
    remove_nw_id smallint )
returns void as $$
begin
    delete from hosts where nw_id = remove_nw_id;
    delete from networks where nw_id = remove_nw_id;
end;
$$ language plpgsql;
alter function remove_network(varchar,smallint) owner to dbaodin;

-- get_networks
create or replace function get_networks(
    ticket varchar(255),
    get_nw_id smallint DEFAULT NULL )
returns SETOF refcursor AS $$
declare
ref1 refcursor;
begin
open ref1 for
    SELECT nw_id, nw_base, nw_cidr, nw_description FROM networks WHERE (get_nw_id IS NULL or nw_id = get_nw_id) ORDER BY inet(nw_base); 
return next ref1;
end;
$$ language plpgsql;
alter function get_networks(varchar,smallint) owner to dbaodin;

-- update_network
create or replace function update_network(
    ticket varchar(255), 
    networkid smallint,
    networkdescription varchar(2000) )
returns void as $$
begin
    UPDATE networks SET nw_description = networkdescription WHERE nw_id = networkid;
end;
$$ language plpgsql;
alter function update_network(varchar,smallint,varchar) owner to dbaodin;


-- get_hosts
-- mask values: 
-- free (1000), free but seen (0100), taken (0010), taken not seen (0001), show all (1111)
create or replace function get_hosts(
    ticket varchar(255),
    get_nw_id smallint DEFAULT NULL,
    page_offset integer default 0,
    items_per_page integer default 100,
    search_string varchar default NULL,
    search_bit_mask smallint default 0)
returns SETOF refcursor AS $$
declare
    host_not_seen_time_limit smallint;
    ref1 refcursor;
begin
    SELECT s_value from settings WHERE s_name = 'host_not_seen_time_limit' INTO host_not_seen_time_limit;
open ref1 for
    WITH host_data AS (
        SELECT
            hosts.*,
            CASE
                WHEN usr_id <> 0 AND (host_last_seen < NOW() - host_not_seen_time_limit * interval '1 days' OR host_last_seen IS NULL) THEN 8 -- taken but not seen
                WHEN usr_id <> 0 AND host_last_seen > NOW() - host_not_seen_time_limit * interval '1 days' THEN 4 -- red, taken and seen
                WHEN usr_id = 0 AND host_last_seen > NOW() - host_not_seen_time_limit * interval '1 days' THEN 2 -- not taken but seen
                WHEN usr_id = 0 AND (host_last_seen < NOW() - host_not_seen_time_limit * interval '1 days' OR host_last_seen IS NULL) THEN 1 -- not taken, not seen
            END as status FROM hosts ),
    reserve_check AS (
        SELECT
            rch.host_ip,
            rcu.usr_usern,
            rcu.usr_email,
            CASE
                WHEN rch.token_usr <> 0 AND (token_timestamp > NOW() - interval '10 minutes') THEN true
                WHEN rch.token_usr = 0 OR token_timestamp < NOW() - interval '10 minutes' THEN false
            END as reserved_status FROM hosts rch LEFT OUTER JOIN users rcu ON ( rch.token_usr = rcu.usr_id ) )
    SELECT
        h.host_ip,
        h.usr_id,
        h.nw_id,
        h.host_name,
        h.host_data,
        h.host_description,
        to_char(h.host_leased, 'YYYY-MM-DD HH24:MI:SS') as host_leased,
        to_char(h.host_last_seen, 'YYYY-MM-DD HH24:MI:SS') as last_seen,
        to_char(h.host_last_scanned, 'YYYY-MM-DD HH24:MI:SS') as last_scanned,
        to_char(h.host_last_notified, 'YYYY-MM-DD HH24:MI:SS') as last_notified,
        to_char(h.host_leased, 'YYYY-MM-DD HH24:MI:SS') as host_leased,
        to_char(h.host_lease_expiry, 'YYYY-MM-DD HH24:MI:SS') as host_lease_expiry,
        u.usr_usern,
        u.usr_firstn,
        u.usr_lastn,
        u.usr_email,
        h.status,
        t.reserved_status,
        t.usr_usern as reserved_by_usern,
        t.usr_email as reserved_by_email,
        count(*) OVER() as total_rows,
        greatest(0,(count(*) OVER()) - (items_per_page * (page_offset+1))) as remaining_rows,
        ceil(count(*) OVER()::float/items_per_page) as total_pages
    FROM host_data h LEFT OUTER JOIN users u ON (h.usr_id = u.usr_id) LEFT OUTER JOIN reserve_check t ON (t.host_ip = h.host_ip)
    WHERE
        (get_nw_id IS NULL or h.nw_id = get_nw_id) AND
        (search_string is NULL or (
            (lower(h.host_description) ~ lower(search_string)) OR
            (lower(h.host_name) ~ lower(search_string)) OR
            (lower(h.host_data) ~ lower(search_string)) OR
            (h.host_ip ~ search_string))
        ) AND
        (search_bit_mask = 0 OR 0 <> (h.status & search_bit_mask))
    ORDER BY inet(h.host_ip) LIMIT items_per_page offset(items_per_page * page_offset);
return next ref1;
end;
$$ language plpgsql;
alter function get_hosts(varchar,smallint,integer,integer,varchar,smallint) owner to dbaodin;

-- update_host
-- Will update host with hostname and description
create or replace function update_host(
    ticket varchar(255),
    host_to_update varchar(36),
    cur_usr_id smallint,
    host_new_name varchar(255),
    host_desc varchar(2000))
returns void as $$
begin
    IF ( host_new_name !~ '[a-zA-Z0-9]+') THEN
        RAISE 'Hostname is empty!';
    END IF;
    IF ( host_desc !~ '[a-zA-Z0-9]+') THEN
        RAISE 'Host description is empty!';
    END IF;
    UPDATE hosts
    SET
        host_description = host_desc,
        host_name = host_new_name
    WHERE
        host_ip = host_to_update AND
        usr_id = cur_usr_id;
    PERFORM add_log_entry( ticket, cur_usr_id, host_to_update, 'Host details updated, new hostname = "' || host_new_name || '", new description = "' || host_desc || '".' );
end;
$$ language plpgsql;
alter function update_host(varchar,varchar,smallint,varchar,varchar) owner to dbaodin;

-- get_reserved
-- This function will get all hosts reserved by a user
-- Input: session key, user id
-- Output: reserved host_ips of the user, otherwise null
create or replace function get_reserved(
    ticket varchar(255),
    user_id smallint )
returns SETOF refcursor AS $$
declare
ref1 refcursor;
begin
open ref1 for
    SELECT host_ip FROM hosts WHERE token_usr = user_id AND token_timestamp > NOW() - interval '10 minutes' ORDER BY inet(host_ip); 
return next ref1;
end;
$$ language plpgsql;
alter function get_reserved(varchar,smallint) owner to dbaodin;

-- get_user_hosts
-- This function will get all hosts leased by a user
-- Input: session key, user id
-- Output: leased host_ips, host_name and host_description of the user, otherwise null
create or replace function get_user_hosts(
    ticket varchar(255),
    user_id smallint )
returns SETOF refcursor AS $$
declare
    host_not_seen_time_limit smallint;
    ref1 refcursor;
begin
open ref1 for
    SELECT host_ip, host_name, host_description, host_lease_expiry, host_last_seen,
    CASE
        WHEN (host_last_seen < NOW() - host_not_seen_time_limit * interval '1 days' OR host_last_seen IS NULL) THEN 8 -- not seen
        WHEN (host_last_seen > NOW() - host_not_seen_time_limit * interval '1 days') THEN 4 -- seen
    END as status
    FROM hosts WHERE usr_id = user_id ORDER BY inet(host_ip); 
return next ref1;
end;
$$ language plpgsql;
alter function get_user_hosts(varchar,smallint) owner to dbaodin;

-- reserve_host
-- This function will be used to preliminary book hosts for reservation
-- Input:  session key, hosts.host_ip, current users.usr_id
-- Output: false if booking failed, true if successful
create or replace function reserve_host(
    ticket varchar(255),
    host_to_reserve varchar(36),
    cur_usr_id smallint )
returns boolean as $$
declare
    host_already_reserved text;
begin
    select host_ip into host_already_reserved from hosts where host_ip = host_to_reserve AND token_timestamp > NOW() - interval '10 minutes' AND token_usr != cur_usr_id LIMIT 1;
    IF host_already_reserved <> '' THEN
        RETURN false;
    ELSE
        update hosts SET token_usr=cur_usr_id WHERE host_ip = host_to_reserve;
        update hosts SET token_timestamp = NOW() WHERE token_usr=cur_usr_id AND (token_timestamp > NOW() - interval '10 minutes' or token_timestamp IS NULL);
    END IF;
    RETURN true;
end;
$$ language plpgsql;
alter function reserve_host(varchar,varchar,smallint) owner to dbaodin;

-- unreserve_host
-- This function will be used to remove a reserveration of a host
-- Input:  session key, hosts.host_ip, current users.usr_id
create or replace function unreserve_host(
    ticket varchar(255),
    host_to_remove varchar(36),
    cur_usr_id smallint )
returns void as $$
begin
    update hosts SET 
        token_usr=0,
        token_timestamp = NULL
    WHERE host_ip = host_to_remove AND token_usr = cur_usr_id;
end;
$$ language plpgsql;
alter function unreserve_host(varchar,varchar,smallint) owner to dbaodin;

-- lease_host
-- This function leases the host and is called after reserving the host
-- Input: session key, hosts.host_ip, current users.usr_id, new host description
-- Output: false if no reserved host was found, true if successful
create or replace function lease_host(
    ticket varchar(255),
    host_to_lease varchar(36),
    cur_usr_id smallint,
    host_new_name varchar(255) default NULL,
    host_desc varchar(2000) default NULL)
returns boolean as $$
declare
    host_already_reserved text;
    max_lease_time smallint;
begin
    IF ( host_new_name !~ '[a-zA-Z0-9]+') THEN
        RAISE 'Hostname is empty!';
    END IF;
    IF ( host_desc !~ '[a-zA-Z0-9]+') THEN
        RAISE 'Host description is empty!';
    END IF;
    SELECT s_value from settings WHERE s_name = 'host_max_lease_time' INTO max_lease_time;
    select host_ip into host_already_reserved from hosts where host_ip = host_to_lease AND token_timestamp > NOW() - interval '10 minutes' AND token_usr != cur_usr_id LIMIT 1;
    IF host_already_reserved <> '' THEN
        RETURN false;
    ELSE
        UPDATE hosts
        SET
            usr_id = cur_usr_id,
            host_leased = now(),
            host_lease_expiry = NOW() + max_lease_time * interval '1 days',
            host_name  = host_new_name,
            host_description = host_desc,
            token_timestamp = NULL,
            token_usr = DEFAULT,
            next_scan_prioritized = true
        WHERE
            host_ip = host_to_lease AND
            token_usr = cur_usr_id AND
            token_timestamp > NOW() - interval '10 minutes';
    PERFORM add_log_entry( ticket, cur_usr_id, host_to_lease, 'Host leased, hostname = "' || host_new_name || '", description = "' || host_desc || '".' );
    RETURN true;
    END IF;
end;
$$ language plpgsql;
alter function lease_host(varchar,varchar,smallint,varchar,varchar) owner to dbaodin;

-- extend_lease
-- This function extends the leases of a host for the current user
-- Input: session key, hosts.host_ip, current users.usr_id
-- Output: false if no reserved host was found, true if successful
create or replace function extend_lease(
    ticket varchar(255),
    host_to_lease varchar(36),
    cur_usr_id smallint)
returns boolean as $$
declare
    max_lease_time smallint;
begin
    SELECT s_value from settings WHERE s_name = 'host_max_lease_time' INTO max_lease_time;
    UPDATE hosts
    SET
        host_leased = now(),
        host_lease_expiry = NOW() + max_lease_time * interval '1 days',
        token_timestamp = NULL,
        token_usr = DEFAULT
    WHERE
        host_ip = host_to_lease AND
        usr_id = cur_usr_id;
    PERFORM add_log_entry( ticket, cur_usr_id, host_to_lease, 'Host-lease extended, new expiry = ' || now() + max_lease_time * interval '1 days' || '.' );
    RETURN true;
end;
$$ language plpgsql;
alter function extend_lease(varchar,varchar,smallint) owner to dbaodin;


-- terminate_lease
-- This function leases the host and is called after reserving the host
-- Input: session key, hosts.host_ip, current users.usr_id, new host description
-- Output: false if no reserved host was found, true if successful
create or replace function terminate_lease(
    ticket varchar(255),
    host_to_terminate varchar(36),
    cur_usr_id smallint)
returns void as $$
begin
    UPDATE hosts
    SET
        usr_id = DEFAULT,
        host_leased = NULL,
        host_lease_expiry = NULL,
        host_description = NULL,
        host_name = NULL,
        token_timestamp = NULL,
        token_usr = DEFAULT
    WHERE
        host_ip = host_to_terminate AND
        usr_id = cur_usr_id;
    PERFORM add_log_entry( ticket, cur_usr_id, host_to_terminate, 'Lease terminated' );
end;
$$ language plpgsql;
alter function terminate_lease(varchar,varchar,smallint) owner to dbaodin;

-- transfer_lease
-- This function will transfer an existing lease from one user to another (only available to admins)
create or replace function transfer_lease(
    ticket varchar(255),
    host_to_transfer varchar(36),
    cur_usr_id smallint,
    new_usr_id smallint,
    admin_usr_id smallint)
returns void as $$
declare
    from_user varchar;
    to_user varchar;
begin
    SELECT usr_firstn || ' ' || usr_lastn FROM users WHERE usr_id = cur_usr_id INTO from_user;
    SELECT usr_firstn || ' ' || usr_lastn FROM users WHERE usr_id = new_usr_id INTO to_user;
    UPDATE hosts
    SET
        usr_id = new_usr_id
    WHERE
        host_ip = host_to_transfer AND
        usr_id = cur_usr_id;
    PERFORM add_log_entry( ticket, admin_usr_id, host_to_transfer, 'Host transfered from user ' || from_user || ' to ' || to_user );
end;
$$ language plpgsql;
alter function transfer_lease(varchar,varchar,smallint,smallint,smallint) owner to dbaodin;

-- force_host_scan
-- This function, available by all users, will force a scan of the target host, regardless of when it was scanned last
create or replace function force_host_scan(
    ticket varchar(255),
    host_to_scan varchar(36))
returns void as $$
begin
    UPDATE hosts
    SET
        next_scan_prioritized = true
    WHERE
        host_ip = host_to_scan;
end;
$$ language plpgsql;
alter function force_host_scan(varchar,varchar) owner to dbaodin;

-- get_network_users
create or replace function get_network_users(
    ticket varchar(255),
    get_nw_id smallint DEFAULT NULL )
returns SETOF refcursor AS $$
declare
ref1 refcursor;
begin
open ref1 for
    SELECT DISTINCT u.usr_id,u.usr_firstn,u.usr_lastn,u.usr_usern FROM hosts h LEFT OUTER JOIN users u ON ( h.usr_id = u.usr_id ) WHERE nw_id = get_nw_id AND h.usr_id <> 0;
return next ref1;
end;
$$ language plpgsql;
alter function get_network_users(varchar,smallint) owner to dbaodin;

-- get_number_of_hosts_in_network
create or replace function get_number_of_hosts_in_network(
    network inet)
returns integer as $$
begin
    RETURN pow( 2, ( 32 - masklen(network) ) ) - 2;
end;
$$ language plpgsql;

-- get_hosts_in_network
create or replace function get_hosts_in_network(
    network inet)
returns SETOF varchar as $$
declare
    nhosts integer;
begin
    nhosts = pow( 2, ( 32 - masklen(network) ) ) - 2;
    FOR i in 1..nhosts LOOP
        RETURN QUERY SELECT host(host(network(network))::inet + i)::varchar;
    END LOOP;
end;
$$ language plpgsql;


