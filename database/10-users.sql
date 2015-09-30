\c odin;

create extension pgcrypto;

create sequence sq_users_id maxvalue 32700 start with 1;
alter sequence sq_users_id owner to dbaodin;

create table users (
        usr_id smallint primary key default nextval('sq_users_id'),
        usr_usern varchar(45) not null,
        usr_lastn varchar(45) null,
        usr_firstn varchar(45) null,
        usr_pwd varchar(100) not null,
        usr_email varchar(128),
        usr_session_key varchar(255),
        usr_last_touch timestamp
        );
create unique index uni_users_usern on users(usr_usern);
create unique index uni_usr_session_key on users(usr_session_key);
alter table users owner to dbaodin;

-- 'User' stored procedures
--
-- add_user
create or replace function add_user(
    username varchar(45), 
    password varchar(100), 
    firstname varchar(45), 
    lastname varchar(45), 
    email varchar(128) )
returns void as $$
begin
    insert into users( usr_usern, usr_pwd, usr_firstn, usr_lastn, usr_email ) values( username, crypt( password, gen_salt('md5') ), firstname, lastname, email );
end;
$$ language plpgsql;
alter function add_user(varchar,varchar,varchar,varchar,varchar) owner to dbaodin;

-- get_users
create or replace function get_users(
    get_usr_id smallint DEFAULT NULL )
returns SETOF refcursor AS $$
declare
ref1 refcursor;
begin
open ref1 for
    SELECT usr_id, usr_usern, usr_lastn, usr_firstn, usr_email FROM users WHERE (get_usr_id IS NULL or usr_id = get_usr_id); 
return next ref1;
end;
$$ language plpgsql;
alter function get_users(smallint) owner to dbaodin;
    
-- update_user
create or replace function update_user(
    userid smallint,
    username varchar(45),
    password varchar(100),
    firstname varchar(45),
    lastname varchar(45),
    email varchar(128) )
returns void as $$
begin
    UPDATE users SET usr_usern = username, usr_pwd = crypt( password, gen_salt( 'md5' ) ), usr_firstn = firstname, usr_lastn = lastname, usr_email = email WHERE usr_id = userid;
end;
$$ language plpgsql;
alter function update_user(smallint,varchar,varchar,varchar,varchar,varchar) owner to dbaodin;

-- Create our administrator
select add_user( 'admin', '', '', '', '' );
