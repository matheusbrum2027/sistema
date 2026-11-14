-- Lê várias observações de uma vez, sem devolver o documento inteiro da operação.

create or replace function public.fln_anotacoes(p_segredo text, p_ids integer[], p_base smallint default 1)
returns jsonb
language plpgsql
stable
security definer
set search_path to ''
as $function$
declare
  saida jsonb;
begin
  perform fln_privado.conferir(p_segredo);
  select coalesce(jsonb_object_agg(a.transfer_id::text, a.texto), '{}'::jsonb)
    into saida
  from fln_privado.anotacao a
  where a.base = p_base
    and a.transfer_id = any(coalesce(p_ids, '{}'::integer[]))
    and a.texto <> '';
  return saida;
end
$function$;

revoke all on function public.fln_anotacoes(text, integer[], smallint) from public;
grant execute on function public.fln_anotacoes(text, integer[], smallint) to anon, authenticated, service_role;
