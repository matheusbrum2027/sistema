-- O APK só recebe o aviso da própria audiência. Motorista não vê o aviso de outro motorista nem o do administrador.

create or replace function public.fln_aviso_apk(
  p_segredo text,
  p_token text,
  p_desde bigint,
  p_base smallint default 1
)
returns jsonb
language plpgsql
security definer
set search_path to ''
as $function$
declare
  doc jsonb;
  push jsonb;
  elem jsonb;
  item jsonb;
  i int;
  achou boolean := false;
  gravar boolean := false;
  avisos jsonb;
  ultimo bigint;
  visto_novo text;
  papel text := '';
  mid int := 0;
  filtrados jsonb := '[]'::jsonb;
  destino int;
begin
  perform fln_privado.conferir(p_segredo);
  p_token := lower(regexp_replace(coalesce(p_token, ''), '[^a-f0-9]', '', 'g'));
  if length(p_token) < 32 or p_base not in (1, 2) then
    return jsonb_build_object('ok', false, 'erro', 'token');
  end if;

  select dados into doc from fln_privado.estado where id = p_base for update;
  if doc is null then
    return jsonb_build_object('ok', false, 'erro', 'aparelho');
  end if;

  avisos := public.fln_avisos(p_segredo, p_desde, p_base);
  ultimo := coalesce((avisos ->> 'ultimo')::bigint, greatest(coalesce(p_desde, 0), 0));
  visto_novo := to_char(now() at time zone 'America/Sao_Paulo', 'YYYY-MM-DD HH24:MI');
  push := coalesce(doc -> 'push', '[]'::jsonb);
  if jsonb_typeof(push) <> 'array' then
    return jsonb_build_object('ok', false, 'erro', 'aparelho');
  end if;

  for i in 0 .. jsonb_array_length(push) - 1 loop
    elem := push -> i;
    if coalesce(elem ->> 'kind', '') = 'apk'
       and lower(coalesce(elem ->> 'token', '')) = p_token then
      achou := true;
      papel := case when coalesce(elem ->> 'papel', 'admin') = 'motorista' then 'motorista' else 'admin' end;
      mid := coalesce(nullif(elem ->> 'motorista_id', '')::int, 0);
      if (elem ->> 'desde') is distinct from ultimo::text
         or coalesce(elem ->> 'visto_em', '') < to_char(
           (now() at time zone 'America/Sao_Paulo') - interval '10 minutes',
           'YYYY-MM-DD HH24:MI'
         ) then
        elem := elem || jsonb_build_object('desde', ultimo, 'visto_em', visto_novo);
        push := jsonb_set(push, array[i::text], elem, false);
        gravar := true;
      end if;
    end if;
  end loop;

  if not achou then
    return jsonb_build_object('ok', false, 'erro', 'aparelho');
  end if;
  if gravar then
    update fln_privado.estado
       set dados = jsonb_set(dados, array['push'], push, true)
     where id = p_base;
  end if;

  for item in select value from jsonb_array_elements(coalesce(avisos -> 'itens', '[]'::jsonb)) loop
    if papel = 'motorista' or mid > 0 then
      if coalesce(item ->> 'audiencia', '') = 'motorista' and mid > 0 then
        for destino in
          select case
            when jsonb_typeof(m) = 'number' then (m #>> '{}')::int
            when (m #>> '{}') ~ '^[0-9]+$' then (m #>> '{}')::int
            else 0
          end
          from jsonb_array_elements(coalesce(item -> 'motoristas', '[]'::jsonb)) m
        loop
          if destino = mid then
            filtrados := filtrados || jsonb_build_array(item);
            exit;
          end if;
        end loop;
      end if;
    elsif coalesce(item ->> 'audiencia', 'admin') <> 'motorista' then
      filtrados := filtrados || jsonb_build_array(item);
    end if;
  end loop;

  return jsonb_build_object(
    'ok', true,
    'papel', papel,
    'motorista_id', mid,
    'itens', filtrados,
    'ultimo', ultimo
  );
end
$function$;
