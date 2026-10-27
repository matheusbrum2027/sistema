-- Aplicar no projeto Supabase quando as credenciais existirem.
-- Não executa sozinho. A service_role não entra no navegador.

create table if not exists public.perfis (
  user_id uuid primary key references auth.users (id) on delete cascade,
  papel text not null default 'motorista' check (papel in ('admin', 'motorista')),
  motorista_id bigint,
  criado_em timestamptz not null default now()
);

alter table public.perfis enable row level security;

create policy perfis_ler_o_proprio
  on public.perfis
  for select
  to authenticated
  using (auth.uid() = user_id);

create policy perfis_sem_insercao_publica
  on public.perfis
  for insert
  to authenticated
  with check (false);

create policy perfis_sem_atualizacao_publica
  on public.perfis
  for update
  to authenticated
  using (false);

create policy perfis_sem_exclusao_publica
  on public.perfis
  for delete
  to authenticated
  using (false);
