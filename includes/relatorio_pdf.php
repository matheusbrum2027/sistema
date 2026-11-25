<?php
declare(strict_types=1);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

/** @return array{de:string,ate:string,mot:int,estado:string,q:string,rotulo:string,motorista:string,situacao:string,viagens:list<array>,lancamentos:list<array>,totais:array} */
function relatorio_consulta(array $db): array
{
    $de = (string) ($_GET['de'] ?? '');
    $ate = (string) ($_GET['ate'] ?? '');
    $mes = (string) ($_GET['mes'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $de)) {
        $de = '';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) {
        $ate = '';
    }
    if ((string) ($_GET['limpar'] ?? '') !== '1' && $de === '' && $ate === '' && preg_match('/^\d{4}-\d{2}$/', $mes)) {
        $de = $mes . '-01';
        $ate = fim_do_mes($de);
    }
    if ((string) ($_GET['limpar'] ?? '') !== '1' && $de === '' && $ate === '') {
        $hoje = today();
        $de = $hoje->format('Y-m-01');
        $ate = fim_do_mes($de);
    }
    if ($de !== '' && $ate !== '' && $ate < $de) {
        [$de, $ate] = [$ate, $de];
    }
    $mot = (int) ($_GET['mot'] ?? 0);
    $estado = (string) ($_GET['estado'] ?? '');
    $estados = ['', 'todas', 'pendente', 'reserva', 'andamento', 'concluida', 'cancelada'];
    if (!in_array($estado, $estados, true)) {
        $estado = '';
    }
    $q = trim((string) ($_GET['q'] ?? ''));
    $qLow = mb_strtolower($q);
    $porTransfer = [];
    foreach ($db['transfers'] as $trip) {
        $porTransfer[(int) ($trip['id'] ?? 0)] = (int) ($trip['motorista_id'] ?? 0);
    }
    $viagens = [];
    foreach ($db['transfers'] as $trip) {
        $data = (string) ($trip['data'] ?? '');
        if ($de !== '' && $data < $de) {
            continue;
        }
        if ($ate !== '' && $data > $ate) {
            continue;
        }
        $status = (string) ($trip['status'] ?? '');
        if ($estado === '') {
            if ($status === 'cancelada') {
                continue;
            }
        } elseif ($estado !== 'todas' && $status !== $estado) {
            continue;
        }
        if ($mot > 0 && (int) ($trip['motorista_id'] ?? 0) !== $mot) {
            continue;
        }
        if ($qLow !== '') {
            $blob = mb_strtolower((string) ($trip['cliente'] ?? '') . ' ' . (string) ($trip['origem'] ?? '') . ' ' . (string) ($trip['destino'] ?? '') . ' ' . (string) ($trip['id'] ?? ''));
            if (!str_contains($blob, $qLow)) {
                continue;
            }
        }
        $viagens[] = $trip;
    }
    usort($viagens, static function (array $a, array $b): int {
        return [((string) ($a['data'] ?? '')), ((string) ($a['hora'] ?? '')), (int) ($a['id'] ?? 0)]
            <=> [((string) ($b['data'] ?? '')), ((string) ($b['hora'] ?? '')), (int) ($b['id'] ?? 0)];
    });
    $lancamentos = [];
    foreach ($db['lancamentos'] as $linha) {
        if (($linha['status'] ?? '') === 'cancelado') {
            continue;
        }
        $data = (string) ($linha['data'] ?? '');
        if ($de !== '' && $data < $de) {
            continue;
        }
        if ($ate !== '' && $data > $ate) {
            continue;
        }
        if ($mot > 0) {
            $mid = (int) ($linha['motorista_id'] ?? 0);
            if ($mid <= 0) {
                $mid = $porTransfer[(int) ($linha['transfer_id'] ?? 0)] ?? 0;
            }
            if ($mid !== $mot) {
                continue;
            }
        }
        if ($qLow !== '') {
            $blob = mb_strtolower((string) ($linha['descricao'] ?? '') . ' ' . (string) ($linha['cliente'] ?? '') . ' ' . (string) ($linha['categoria'] ?? ''));
            if (!str_contains($blob, $qLow)) {
                continue;
            }
        }
        $lancamentos[] = $linha;
    }
    usort($lancamentos, static fn(array $a, array $b): int => [((string) ($b['data'] ?? '')), (int) ($b['id'] ?? 0)] <=> [((string) ($a['data'] ?? '')), (int) ($a['id'] ?? 0)]);
    $receita = 0.0;
    $recebido = 0.0;
    $vista = 0.0;
    $boleto = 0.0;
    $repasse = 0.0;
    foreach ($viagens as $trip) {
        $valor = (float) ($trip['valor'] ?? 0);
        $receita += $valor;
        if (!empty($trip['pago'])) {
            $recebido += $valor;
        } elseif (!empty($trip['faturado'])) {
            $boleto += $valor;
        } else {
            $vista += $valor;
        }
        if ((string) ($trip['status'] ?? '') !== 'cancelada') {
            $repasse += (float) ($trip['repasse'] ?? 0);
        }
    }
    $despesa = 0.0;
    foreach ($lancamentos as $linha) {
        if (($linha['tipo'] ?? '') === 'saida') {
            $despesa += (float) ($linha['valor'] ?? 0);
        }
    }
    $nomeMot = 'Todos';
    if ($mot > 0) {
        $nomeMot = nome_motorista($db, $mot);
    }
    $situacao = match ($estado) {
        'todas' => 'Todas, inclusive canceladas',
        'pendente' => 'Pendente',
        'reserva' => 'Reserva',
        'andamento' => 'Em andamento',
        'concluida' => 'Concluída',
        'cancelada' => 'Cancelada',
        default => 'Ativas, sem canceladas',
    };
    $rotulo = $de !== '' && $ate !== '' ? br_date($de) . ' a ' . br_date($ate) : 'Todas as reservas';
    return [
        'de' => $de,
        'ate' => $ate,
        'mot' => $mot,
        'estado' => $estado,
        'q' => $q,
        'rotulo' => $rotulo,
        'motorista' => $nomeMot,
        'situacao' => $situacao,
        'viagens' => $viagens,
        'lancamentos' => $lancamentos,
        'totais' => [
            'viagens' => count($viagens),
            'receita' => $receita,
            'recebido' => $recebido,
            'vista' => $vista,
            'boleto' => $boleto,
            'repasse' => $repasse,
            'despesa' => $despesa,
            'lancamentos' => count($lancamentos),
        ],
    ];
}

function relatorio_pdf_bytes(array $db, array $consulta): string
{
    $doc = new RelatorioPdf($db, $consulta);
    return $doc->bytes();
}

function relatorio_arquivo_pdf(array $consulta): string
{
    $de = str_replace('-', '', (string) ($consulta['de'] ?? ''));
    $ate = str_replace('-', '', (string) ($consulta['ate'] ?? ''));
    return 'Relatorio ' . $de . ' a ' . $ate . '.pdf';
}

final class RelatorioPdf
{
    private const LARGURA = 841.89;
    private const ALTURA = 595.28;
    private const MARGEM = 24.0;
    private const NAVY = [0.102, 0.137, 0.196];
    private const ORANGE = [0.949, 0.420, 0.102];
    private const INK = [0.110, 0.141, 0.188];
    private const MUTED = [0.420, 0.451, 0.502];
    private const GELO = [0.953, 0.961, 0.973];
    private const LINHA = [0.890, 0.906, 0.933];
    private const BRANCO = [1, 1, 1];

    /** @var list<FolhaPdf> */
    private array $paginas = [];
    private FolhaPdf $pagina;
    private float $y = 0;
    private array $db;
    private array $consulta;
    private bool $capa = true;

    public function __construct(array $db, array $consulta)
    {
        $this->db = $db;
        $this->consulta = $consulta;
    }

    public function bytes(): string
    {
        $this->novaPagina();
        $this->criterio();
        $this->totais();
        $this->secao('Reservas do período');
        $this->tabelaReservas();
        $this->secao('Lançamentos do período');
        $this->tabelaLancamentos();
        $this->nota('Cada linha acima entrou nos totais deste documento. Reserva cancelada só aparece quando o filtro pede. O pro-labore soma o repasse gravado na reserva. Despesa soma as saídas dos lançamentos.');
        return $this->montar();
    }

    private function novaPagina(): void
    {
        $this->pagina = new FolhaPdf();
        $this->paginas[] = $this->pagina;
        $this->pagina->ret(0, 0, self::LARGURA, self::ALTURA, self::BRANCO);
        $s = is_array($this->db['settings'] ?? null) ? $this->db['settings'] : [];
        $marca = mb_strtoupper(trim((string) ($s['nome'] ?? '')) ?: 'TRANSFER FLN');
        $razao = trim((string) ($s['razao'] ?? '')) ?: 'Fln Transfer Executivo e Turismo';
        $local = function_exists('reserva_cidade_uf') ? reserva_cidade_uf($s) : '';
        $altura = $this->capa ? 78.0 : 46.0;
        $topo = self::ALTURA;
        $this->pagina->ret(0, $topo - $altura, self::LARGURA, $altura, self::NAVY);
        $this->pagina->ret(0, $topo - $altura - 3, self::LARGURA, 3, self::ORANGE);
        $this->pagina->txt(self::MARGEM, $topo - 22, 8, $this->corta($marca, 42), true, self::ORANGE);
        $this->pagina->txt(self::MARGEM, $topo - 38, 13, $this->capa ? 'Relatório financeiro' : 'Relatório financeiro · continuação', true, self::BRANCO);
        if ($this->capa) {
            $this->pagina->txt(self::MARGEM, $topo - 54, 8, $this->corta($razao . ($local !== '' ? ' · ' . $local : ''), 80), false, [0.773, 0.800, 0.839]);
            $this->pagina->txt(self::MARGEM, $topo - 68, 8, $this->corta((string) $this->consulta['rotulo'], 60), false, self::BRANCO);
        }
        $emitido = function_exists('reserva_emitido_texto') ? reserva_emitido_texto() : '';
        $this->pagina->txt(self::LARGURA - self::MARGEM - 150, $topo - 22, 8, $emitido, false, [0.773, 0.800, 0.839]);
        $this->y = $topo - $altura - 16;
        $this->capa = false;
    }

    private function criterio(): void
    {
        $this->pagina->txt(self::MARGEM, $this->y, 8, 'Critério desta emissão', true, self::ORANGE);
        $this->y -= 14;
        $partes = [
            'Período ' . (string) $this->consulta['rotulo'],
            'Motorista ' . (string) $this->consulta['motorista'],
            'Situação ' . (string) $this->consulta['situacao'],
            'Busca ' . ((string) $this->consulta['q'] !== '' ? (string) $this->consulta['q'] : 'nenhuma'),
        ];
        $this->pagina->txt(self::MARGEM, $this->y, 8, $this->corta(implode('   ·   ', $partes), 150), false, self::INK);
        $this->y -= 16;
    }

    private function totais(): void
    {
        $t = $this->consulta['totais'];
        $itens = [
            ['Reservas', (string) $t['viagens']],
            ['Receita', brl((float) $t['receita'])],
            ['Recebido', brl((float) $t['recebido'])],
            ['Na hora', brl((float) $t['vista'])],
            ['Boleto', brl((float) $t['boleto'])],
            ['Pro-labore', brl((float) $t['repasse'])],
            ['Despesas', brl((float) $t['despesa'])],
        ];
        $largura = (self::LARGURA - (self::MARGEM * 2) - 24) / 7;
        foreach ($itens as $i => [$rotulo, $valor]) {
            $x = self::MARGEM + ($i * ($largura + 4));
            $this->pagina->ret($x, $this->y - 28, $largura, 32, self::GELO);
            $this->pagina->txt($x + 6, $this->y - 2, 6.5, mb_strtoupper($rotulo), true, self::ORANGE);
            $this->pagina->txt($x + 6, $this->y - 16, 8, $this->corta($valor, 16), true, self::INK);
        }
        $this->y -= 44;
    }

    private function secao(string $titulo): void
    {
        $this->garantir(28);
        $this->pagina->txt(self::MARGEM, $this->y, 11, $titulo, true, self::NAVY);
        $this->pagina->linha(self::MARGEM, $this->y - 4, self::LARGURA - self::MARGEM, $this->y - 4, self::ORANGE);
        $this->y -= 18;
    }

    private function tabelaReservas(): void
    {
        $cols = [36, 48, 32, 108, 188, 96, 62, 58, 52, 58];
        $cab = ['Nº', 'Data', 'Hora', 'Cliente', 'Trajeto', 'Motorista', 'Status', 'Valor', 'Conta', 'Repasse'];
        $this->cabecalho($cab, $cols);
        if ($this->consulta['viagens'] === []) {
            $this->garantir(16);
            $this->pagina->txt(self::MARGEM, $this->y, 8, 'Nenhuma reserva neste filtro.', false, self::MUTED);
            $this->y -= 16;
            return;
        }
        $somaValor = 0.0;
        $somaRepasse = 0.0;
        foreach ($this->consulta['viagens'] as $i => $trip) {
            $this->garantir(14, $cab, $cols);
            if ($i % 2 === 0) {
                $this->pagina->ret(self::MARGEM, $this->y - 3, self::LARGURA - (self::MARGEM * 2), 13, self::GELO);
            }
            $valor = (float) ($trip['valor'] ?? 0);
            $repasse = (string) ($trip['status'] ?? '') === 'cancelada' ? 0.0 : (float) ($trip['repasse'] ?? 0);
            $somaValor += $valor;
            $somaRepasse += $repasse;
            $conta = !empty($trip['pago']) ? 'Recebido' : (!empty($trip['faturado']) ? 'Boleto' : 'Na hora');
            $tipo = trim(preg_replace('/\s+/u', ' ', strip_tags(tipo_badges($trip))) ?? '');
            $trajeto = short_place((string) ($trip['origem'] ?? '')) . ' → ' . short_place((string) ($trip['destino'] ?? ''));
            $celulas = [
                '#' . (int) ($trip['id'] ?? 0),
                br_date((string) ($trip['data'] ?? '')),
                substr((string) ($trip['hora'] ?? ''), 0, 5),
                (string) ($trip['cliente'] ?? ''),
                $trajeto . ($tipo !== '' ? ' · ' . $tipo : ''),
                nome_motorista($this->db, $trip['motorista_id'] ?? 0),
                status_label((string) ($trip['status'] ?? '')),
                brl($valor),
                $conta,
                $repasse > 0 ? brl($repasse) : '—',
            ];
            $this->linha($celulas, $cols, false);
        }
        $this->garantir(16, $cab, $cols);
        $this->pagina->linha(self::MARGEM, $this->y + 10, self::LARGURA - self::MARGEM, $this->y + 10, self::LINHA);
        $this->linha(['', '', '', count($this->consulta['viagens']) . ' reservas', '', '', 'Soma', brl($somaValor), '', brl($somaRepasse)], $cols, true);
    }

    private function tabelaLancamentos(): void
    {
        $cols = [52, 250, 58, 110, 120, 70, 62, 62];
        $cab = ['Data', 'Descrição', 'Tipo', 'Categoria', 'Motorista', 'Valor', 'Estado', 'Meio'];
        $this->cabecalho($cab, $cols);
        if ($this->consulta['lancamentos'] === []) {
            $this->garantir(16);
            $this->pagina->txt(self::MARGEM, $this->y, 8, 'Nenhum lançamento neste filtro.', false, self::MUTED);
            $this->y -= 16;
            return;
        }
        $entradas = 0.0;
        $saidas = 0.0;
        foreach ($this->consulta['lancamentos'] as $i => $linha) {
            $this->garantir(14, $cab, $cols);
            if ($i % 2 === 0) {
                $this->pagina->ret(self::MARGEM, $this->y - 3, self::LARGURA - (self::MARGEM * 2), 13, self::GELO);
            }
            $valor = (float) ($linha['valor'] ?? 0);
            if (($linha['tipo'] ?? '') === 'saida') {
                $saidas += $valor;
            } else {
                $entradas += $valor;
            }
            $mid = (int) ($linha['motorista_id'] ?? 0);
            $celulas = [
                br_date((string) ($linha['data'] ?? '')),
                (string) ($linha['descricao'] ?? ''),
                ($linha['tipo'] ?? '') === 'saida' ? 'Despesa' : 'Receita',
                (string) ($linha['categoria'] ?? ''),
                $mid > 0 ? nome_motorista($this->db, $mid) : '—',
                brl($valor),
                status_label((string) (($linha['status'] ?? '') === 'pago' ? 'pago' : 'pendente')),
                trim((string) ($linha['meio'] ?? '')) !== '' ? (string) $linha['meio'] : '—',
            ];
            $this->linha($celulas, $cols, false);
        }
        $this->garantir(16, $cab, $cols);
        $this->pagina->linha(self::MARGEM, $this->y + 10, self::LARGURA - self::MARGEM, $this->y + 10, self::LINHA);
        $this->linha(['', count($this->consulta['lancamentos']) . ' lançamentos', '', '', 'Receitas ' . brl($entradas), 'Saídas ' . brl($saidas), '', ''], $cols, true);
    }

    /** @param list<string> $cab @param list<int> $cols */
    private function cabecalho(array $cab, array $cols): void
    {
        $this->garantir(16);
        $this->pagina->ret(self::MARGEM, $this->y - 4, self::LARGURA - (self::MARGEM * 2), 14, self::NAVY);
        $x = self::MARGEM + 3;
        foreach ($cab as $i => $rotulo) {
            $this->pagina->txt($x, $this->y, 6.5, $rotulo, true, self::BRANCO);
            $x += $cols[$i];
        }
        $this->y -= 16;
    }

    /** @param list<string> $celulas @param list<int> $cols */
    private function linha(array $celulas, array $cols, bool $negrito): void
    {
        $x = self::MARGEM + 3;
        foreach ($celulas as $i => $texto) {
            $max = max(4, (int) floor($cols[$i] / 4.2));
            $this->pagina->txt($x, $this->y, 7, $this->corta($texto, $max), $negrito, self::INK);
            $x += $cols[$i];
        }
        $this->y -= 13;
    }

    /** @param list<string> $cab @param list<int> $cols */
    private function garantir(float $altura, array $cab = [], array $cols = []): void
    {
        if ($this->y - $altura >= 32) {
            return;
        }
        $this->novaPagina();
        if ($cab !== []) {
            $this->cabecalho($cab, $cols);
        }
    }

    private function nota(string $texto): void
    {
        $this->garantir(24);
        $this->pagina->txt(self::MARGEM, $this->y, 7.5, $this->corta($texto, 170), false, self::MUTED);
        $this->y -= 12;
    }

    private function corta(string $texto, int $max): string
    {
        $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? '');
        if (mb_strlen($texto) <= $max) {
            return $texto;
        }
        return rtrim(mb_substr($texto, 0, $max - 3)) . '...';
    }

    private function montar(): string
    {
        $total = count($this->paginas);
        foreach ($this->paginas as $i => $pagina) {
            $pagina->linha(self::MARGEM, 26, self::LARGURA - self::MARGEM, 26, self::LINHA);
            $pagina->txt(self::MARGEM, 14, 7.5, 'Página ' . ($i + 1) . ' de ' . $total . '  ·  Paisagem  ·  ' . (string) $this->consulta['rotulo'], false, self::MUTED);
        }
        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $next = 5;
        $kids = [];
        foreach ($this->paginas as $pagina) {
            $cid = $next++;
            $pid = $next++;
            $stream = $pagina->ops;
            $objs[$cid] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
            $objs[$pid] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::LARGURA . ' ' . self::ALTURA . '] /Contents ' . $cid
                . ' 0 R /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> >>';
            $kids[] = $pid . ' 0 R';
        }
        $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
        ksort($objs);
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objs as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $max = (int) max(array_keys($objs));
        $pdf .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $max; $i++) {
            $pdf .= isset($offsets[$i]) ? sprintf("%010d 00000 n \n", $offsets[$i]) : "0000000000 00000 f \n";
        }
        $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
        return $pdf;
    }
}
