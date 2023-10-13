CREATE ROLE "account.mfa" LOGIN PASSWORD 'password';

create table cases(
    caseid varchar(255) not null,
    description varchar(255) not null
);

GRANT SELECT ON cases TO "account.mfa";

create table user_cases(
    uid bigint not null,
    cases text not null
);

GRANT SELECT, INSERT, UPDATE ON user_cases TO "account.mfa";

create table user_providers(
    uid bigint not null,
    providerid varchar(64) not null,
    enabled boolean not null,
    config text not null
);

GRANT SELECT, INSERT, UPDATE, DELETE ON user_providers TO "account.mfa";









create table email_codes(
    codeid bigserial not null primary key,
    uid bigint not null,
    context varchar(32) not null,
    code varchar(6) not null,
    context_data text default null,
    
    foreign key(uid) references users(uid)
);

GRANT SELECT, INSERT, DELETE ON email_codes TO "account.mfa";
GRANT SELECT, USAGE ON SEQUENCE email_codes_codeid_seq TO "account.mfa";
