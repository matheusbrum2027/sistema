-- Observação interna (só o admin) fica ao lado da observação pública (PDF e motorista).

alter table fln_privado.anotacao
  add column if not exists interna text not null default '';

create or replace function fln_privado.guardar_obs(p_base smallint, item jsonb)
returns jsonb
language plpgsql
security definer
set search_path to ''
as $function$
declare
  tid int;
  nota text;
  interna text;
  tocada boolean;
begin
  tid := coalesce((item ->> 'id')::int, 0);
  tocada := coalesce((item -> 'obs_tocada') = 'true'::jsonb, false);
  if tid > 0
     and (pg_catalog.jsonb_exists(item, 'obs') or pg_catalog.jsonb_exists(item, 'obs_interna'))
     and (tocada or coalesce(item ->> 'obs', '') <> '' or coalesce(item ->> 'obs_interna', '') <> '') then
    nota := left(coalesce(item ->> 'obs', ''), 8000);
    if pg_catalog.jsonb_exists(item, 'obs_interna') then
      interna := left(coalesce(item ->> 'obs_interna', ''), 8000);
    else
      select a.interna into interna
      from fln_privado.anotacao a
      where a.base = p_base and a.transfer_id = tid;
      interna := coalesce(interna, '');
    end if;
    if nota = '' and interna = '' then
      delete from fln_privado.anotacao where base = p_base and transfer_id = tid;
    else
      insert into fln_privado.anotacao (base, transfer_id, texto, interna)
      values (p_base, tid, nota, interna)
      on conflict (base, transfer_id) do update
        set texto = excluded.texto,
            interna = excluded.interna;
    end if;
  end if;
  return item - 'obs' - 'obs_tocada' - 'obs_interna';
end
$function$;

create or replace function public.fln_anotacao_par(
  p_segredo text,
  p_id integer,
  p_base smallint default 1
)
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
  select jsonb_build_object(
    'obs', coalesce(texto, ''),
    'interna', coalesce(interna, '')
  )
    into saida
  from fln_privado.anotacao
  where base = p_base and transfer_id = p_id;
  return coalesce(saida, jsonb_build_object('obs', '', 'interna', ''));
end
$function$;

revoke all on function public.fln_anotacao_par(text, integer, smallint) from public;
grant execute on function public.fln_anotacao_par(text, integer, smallint) to anon, authenticated, service_role;

create or replace function public.fln_obs_reparar(
  p_segredo text,
  p_base smallint,
  p_itens jsonb
)
returns integer
language plpgsql
security definer
set search_path to ''
as $function$
declare
  item jsonb;
  tid int;
  nota text;
  interna text;
  n int := 0;
begin
  perform fln_privado.conferir(p_segredo);
  if jsonb_typeof(p_itens) <> 'array' then
    return 0;
  end if;
  for item in select value from jsonb_array_elements(p_itens) as t(value)
  loop
    tid := coalesce((item ->> 'id')::int, 0);
    if tid <= 0 then
      continue;
    end if;
    nota := left(coalesce(item ->> 'p', ''), 8000);
    interna := left(coalesce(item ->> 'i', ''), 8000);
    if nota = '' and interna = '' then
      delete from fln_privado.anotacao where base = p_base and transfer_id = tid;
    else
      insert into fln_privado.anotacao (base, transfer_id, texto, interna)
      values (p_base, tid, nota, interna)
      on conflict (base, transfer_id) do update
        set texto = excluded.texto,
            interna = excluded.interna;
    end if;
    n := n + 1;
  end loop;
  return n;
end
$function$;

revoke all on function public.fln_obs_reparar(text, smallint, jsonb) from public;
grant execute on function public.fln_obs_reparar(text, smallint, jsonb) to anon, authenticated, service_role;
