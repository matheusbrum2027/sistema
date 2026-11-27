-- Sem a chave obs, a gravação mantém o texto que já está na anotação.
-- O mesmo já acontecia com a observação interna.

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
    if pg_catalog.jsonb_exists(item, 'obs') then
      nota := left(coalesce(item ->> 'obs', ''), 8000);
    else
      select a.texto into nota
      from fln_privado.anotacao a
      where a.base = p_base and a.transfer_id = tid;
      nota := coalesce(nota, '');
    end if;
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
