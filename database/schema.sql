create extension if not exists "pgcrypto";

create table users (
    id bigserial primary key,
    name varchar(255) not null,
    email varchar(255) not null unique,
    password varchar(255) not null,
    role varchar(64) not null,
    mfa_enabled boolean not null default false,
    active boolean not null default true,
    created_at timestamp null,
    updated_at timestamp null
);

create table clients (
    id bigserial primary key,
    name varchar(255) not null,
    nip varchar(32) null,
    address text null,
    contact_name varchar(255) null,
    contact_email varchar(255) null,
    contact_phone varchar(64) null,
    account_manager_id bigint null references users(id) on delete set null,
    status varchar(64) not null default 'active',
    notes text null,
    created_at timestamp null,
    updated_at timestamp null
);

create table client_locations (
    id bigserial primary key,
    client_id bigint not null references clients(id) on delete cascade,
    name varchar(255) not null,
    address text null,
    contact_name varchar(255) null,
    contact_email varchar(255) null,
    contact_phone varchar(64) null,
    location_type varchar(64) null,
    notes text null,
    created_at timestamp null,
    updated_at timestamp null
);

create table audit_templates (
    id bigserial primary key,
    name varchar(255) not null,
    description text null,
    active boolean not null default true,
    created_at timestamp null,
    updated_at timestamp null
);

create table audit_modules (
    id bigserial primary key,
    name varchar(255) not null,
    description text null,
    category varchar(128) null,
    active boolean not null default true,
    sort_order integer not null default 0,
    created_at timestamp null,
    updated_at timestamp null
);

create table audit_template_modules (
    id bigserial primary key,
    audit_template_id bigint not null references audit_templates(id) on delete cascade,
    audit_module_id bigint not null references audit_modules(id) on delete cascade,
    sort_order integer not null default 0,
    unique (audit_template_id, audit_module_id)
);

create table audit_questions (
    id bigserial primary key,
    audit_module_id bigint not null references audit_modules(id) on delete cascade,
    question text not null,
    instruction text null,
    field_type varchar(64) not null,
    is_required boolean not null default false,
    allow_not_applicable boolean not null default true,
    require_comment_when_na boolean not null default false,
    require_photo boolean not null default false,
    require_screenshot boolean not null default false,
    risk_enabled boolean not null default false,
    sort_order integer not null default 0,
    config_json jsonb null,
    active boolean not null default true,
    created_at timestamp null,
    updated_at timestamp null
);

create table recommendation_library (
    id bigserial primary key,
    title varchar(255) not null,
    technical_description text null,
    business_description text null,
    recommendation_text text not null,
    risk_level varchar(32) null,
    priority varchar(32) null,
    suggested_deadline varchar(128) null,
    estimated_hours_min integer null,
    estimated_hours_max integer null,
    global_it_can_do boolean not null default true,
    sales_category varchar(128) null,
    tags_json jsonb null,
    active boolean not null default true,
    created_at timestamp null,
    updated_at timestamp null
);

create table audit_question_recommendations (
    id bigserial primary key,
    audit_question_id bigint not null references audit_questions(id) on delete cascade,
    recommendation_id bigint not null references recommendation_library(id) on delete cascade,
    unique (audit_question_id, recommendation_id)
);

create table audits (
    id bigserial primary key,
    client_id bigint not null references clients(id) on delete restrict,
    client_location_id bigint null references client_locations(id) on delete set null,
    audit_template_id bigint null references audit_templates(id) on delete set null,
    title varchar(255) not null,
    description text null,
    status varchar(64) not null default 'draft',
    scheduled_at timestamp null,
    started_at timestamp null,
    completed_at timestamp null,
    submitted_at timestamp null,
    approved_at timestamp null,
    created_by bigint null references users(id) on delete set null,
    lead_reviewer_id bigint null references users(id) on delete set null,
    created_at timestamp null,
    updated_at timestamp null
);

create table audit_assignees (
    id bigserial primary key,
    audit_id bigint not null references audits(id) on delete cascade,
    user_id bigint not null references users(id) on delete cascade,
    role_in_audit varchar(64) not null default 'auditor',
    unique (audit_id, user_id)
);

create table audit_selected_modules (
    id bigserial primary key,
    audit_id bigint not null references audits(id) on delete cascade,
    audit_module_id bigint not null references audit_modules(id) on delete restrict,
    sort_order integer not null default 0,
    unique (audit_id, audit_module_id)
);

create table audit_answers (
    id bigserial primary key,
    audit_id bigint not null references audits(id) on delete cascade,
    audit_question_id bigint not null references audit_questions(id) on delete restrict,
    audit_module_id bigint not null references audit_modules(id) on delete restrict,
    answered_by bigint null references users(id) on delete set null,
    value_json jsonb null,
    comment text null,
    not_applicable boolean not null default false,
    not_applicable_reason text null,
    risk_level varchar(32) null,
    recommendation_text text null,
    status varchar(64) not null default 'draft',
    sync_status varchar(64) not null default 'synced',
    local_uuid uuid not null default gen_random_uuid(),
    created_at timestamp null,
    updated_at timestamp null,
    unique (audit_id, audit_question_id)
);

create table audit_attachments (
    id bigserial primary key,
    audit_id bigint not null references audits(id) on delete cascade,
    audit_answer_id bigint null references audit_answers(id) on delete cascade,
    uploaded_by bigint null references users(id) on delete set null,
    type varchar(64) not null,
    file_path text not null,
    original_filename varchar(255) not null,
    mime_type varchar(128) null,
    size bigint null,
    description text null,
    sync_status varchar(64) not null default 'synced',
    local_uuid uuid not null default gen_random_uuid(),
    created_at timestamp null,
    updated_at timestamp null
);

create table audit_reports (
    id bigserial primary key,
    audit_id bigint not null references audits(id) on delete cascade,
    report_type varchar(64) not null,
    status varchar(64) not null default 'draft',
    html_content text null,
    pdf_path text null,
    docx_path text null,
    generated_by bigint null references users(id) on delete set null,
    generated_at timestamp null,
    approved_by bigint null references users(id) on delete set null,
    approved_at timestamp null,
    published_to_client boolean not null default false,
    created_at timestamp null,
    updated_at timestamp null,
    unique (audit_id, report_type)
);

create table audit_activity_logs (
    id bigserial primary key,
    audit_id bigint null references audits(id) on delete cascade,
    user_id bigint null references users(id) on delete set null,
    action varchar(128) not null,
    description text null,
    metadata_json jsonb null,
    created_at timestamp null,
    updated_at timestamp null
);

create index clients_account_manager_id_idx on clients(account_manager_id);
create index client_locations_client_id_idx on client_locations(client_id);
create index audits_status_idx on audits(status);
create index audits_client_id_idx on audits(client_id);
create index audit_answers_sync_status_idx on audit_answers(sync_status);
create index audit_attachments_sync_status_idx on audit_attachments(sync_status);
create index audit_activity_logs_audit_id_idx on audit_activity_logs(audit_id);

