-- Fecha o que o painel não usa.
-- rls_auto_enable é gatilho interno da plataforma: ninguém chama pela API.
-- perfis só deixa o usuário autenticado ler a própria linha.

revoke all on function public.rls_auto_enable() from public;
revoke all on function public.rls_auto_enable() from anon;
revoke all on function public.rls_auto_enable() from authenticated;

revoke all on table public.perfis from anon;
revoke all on table public.perfis from authenticated;
grant select on table public.perfis to authenticated;
