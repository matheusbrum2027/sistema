-- Meses em que um motorista tem reserva ativa. A resposta é só a lista de meses.

create or replace function public.fln_meses_motorista(p_segredo text, p_base smallint, p_mot integer)
returns jsonb
language plpgsql
stable
security definer
set search_path to ''
as $function$
declare
  meses jsonb;
begin
  perform fln_privado.conferir(p_segredo);
  if coalesce(p_mot, 0) <= 0 then
    return '[]'::jsonb;
  end if;
  select coalesce(jsonb_agg(mes order by mes desc), '[]'::jsonb)
    into meses
  from (
    select distinct left(x->>'data', 7) as mes
    from fln_privado.estado e
    cross join lateral jsonb_array_elements(e.transfers_doc) x
    where e.id = p_base
      and coalesce(x->>'status', '') <> 'cancelada'
      and left(coalesce(x->>'data', ''), 7) ~ '^\d{4}-\d{2}$'
      and case
            when pg_catalog.jsonb_typeof(x->'motorista_id') = 'number' then (x->>'motorista_id')::int
            else 0
          end = p_mot
  ) s;
  return meses;
end
$function$;

grant execute on function public.fln_meses_motorista(text, smallint, integer) to anon, authenticated, service_role;
