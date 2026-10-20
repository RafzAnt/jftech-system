-- The photo of a NAP box, so the technician can recognise it on the pole. It is stored as base64 text of a picture the browser has
-- already shrunk to about 1000 px, in a table of its own so the list of NAP boxes never loads the pictures.
-- Run this once in the Supabase SQL Editor; running it again changes nothing.

begin;

create table if not exists public.nap_box_photos (
    nap_box_id bigint primary key references public.nap_boxes(id) on delete cascade,
    mime_type varchar(30) not null,
    data text not null,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now()
);

alter table public.nap_box_photos enable row level security;

commit;
