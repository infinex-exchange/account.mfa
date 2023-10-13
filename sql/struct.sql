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
    enabled boolean not null default FALSE,
    config text not null
);

GRANT SELECT, INSERT, UPDATE, DELETE ON user_providers TO "account.mfa";

create table email_codes(
    uid bigint not null,
    action varchar(64) not null,
    context_hash varchar(32) not null,
    code varchar(6) not null
);

GRANT SELECT, INSERT, DELETE ON email_codes TO "account.mfa";
