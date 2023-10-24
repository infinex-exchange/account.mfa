CREATE EXTENSION IF NOT EXISTS timescaledb;

CREATE ROLE "account.mfa" LOGIN PASSWORD 'password';

create table cases(
    caseid varchar(255) not null,
    description varchar(255) not null
);

GRANT SELECT ON cases TO "account.mfa";

create table user_cases(
    uid bigint not null,
    cases text not null,
    
    unique(uid)
);

GRANT SELECT, INSERT, UPDATE ON user_cases TO "account.mfa";

create table user_providers(
    uid bigint not null,
    providerid varchar(64) not null,
    enabled boolean not null default FALSE,
    config text not null,
    
    unique(uid, providerid)
);

GRANT SELECT, INSERT, UPDATE, DELETE ON user_providers TO "account.mfa";

create table email_codes(
    time timestamptz not null default current_timestamp,
    uid bigint not null,
    action varchar(64) not null,
    context_hash varchar(32) not null,
    code varchar(6) not null
);
SELECT create_hypertable('email_codes', 'time');
SELECT add_retention_policy('email_codes', INTERVAL '5 minutes');

GRANT SELECT, INSERT, DELETE ON email_codes TO "account.mfa";
