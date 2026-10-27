<?php
declare(strict_types=1);

function nice_ceil(float $m): float
{
    if ($m <= 0) {
        return 1.0;
    }
    $exp = floor(log10($m));
    $base = 10 ** $exp;
    $frac = $m / $base;
    if ($frac <= 1) {
        $nice = 1;
    } elseif ($frac <= 2) {
        $nice = 2;
    } elseif ($frac <= 2.5) {
        $nice = 2.5;
    } elseif ($frac <= 5) {
        $nice = 5;
    } else {
        $nice = 10;
    }
    return $nice * $base;
}

function n1(float $n): string
{
    return (string) round($n, 1);
}

function fmt_axis(float $v, string $fmt): string
{
    if ($fmt === 'money') {
        if (abs($v) >= 1000) {
            $k = $v / 1000;
            $s = rtrim(rtrim(number_format($k, 1, ',', ''), '0'), ',');
            return 'R$ ' . $s . 'k';
        }
        return 'R$ ' . number_format($v, 0, ',', '.');
    }
    if (abs($v - round($v)) < 0.001) {
        return (string) (int) round($v);
    }
    $s = number_format($v, 2, '.', '');
    $s = rtrim(rtrim($s, '0'), '.');
    return $s === '' ? '0' : $s;
}

function svg_line_chart(array $labels, array $series, array $opt = []): string
{
    $w = 560;
    $h = 248;
    $padL = 54;
    $padR = !empty($opt['showRight']) ? 36 : 14;
    $padT = 14;
    $padB = 30;
    $iw = $w - $padL - $padR;
    $ih = $h - $padT - $padB;
    $n = max(count($labels), 1);

    $left = [0.0];
    $right = [0.0];
    foreach ($series as $s) {
        foreach ($s['data'] as $v) {
            if (($s['axis'] ?? 'left') === 'right') {
                $right[] = (float) $v;
            } else {
                $left[] = (float) $v;
            }
        }
    }

    $maxL = isset($opt['maxLeft']) ? (float) $opt['maxLeft'] : nice_ceil(max($left) * 1.05);
    $maxR = isset($opt['maxRight']) ? (float) $opt['maxRight'] : nice_ceil(max($right) * 1.05);
    if (!empty($opt['minLeft'])) {
        $maxL = max($maxL, (float) $opt['minLeft']);
    }
    if ($maxL <= 0) {
        $maxL = 1;
    }
    if ($maxR <= 0) {
        $maxR = 1;
    }

    $x = static function (int $i) use ($n, $padL, $iw): float {
        if ($n === 1) {
            return $padL + $iw / 2;
        }
        return $padL + ($iw * $i / ($n - 1));
    };
    $yOf = static function (float $v, float $max) use ($padT, $ih): float {
        $plot = $ih - 8;
        $vv = max(0.0, min($max, $v));
        return $padT + 4 + $plot * (1 - ($vv / $max));
    };

    $fmtL = $opt['formatLeft'] ?? 'money';
    $fmtR = $opt['formatRight'] ?? 'num';
    $ticks = (int) ($opt['tickCount'] ?? 4);
    $showRight = !empty($opt['showRight']);

    $svg = '<svg class="chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . e($opt['label'] ?? 'Gráfico') . '">';
    for ($i = 0; $i <= $ticks; $i++) {
        $val = $maxL * ($i / $ticks);
        $y = $yOf($val, $maxL);
        $svg .= '<line x1="' . $padL . '" y1="' . n1($y) . '" x2="' . ($w - $padR) . '" y2="' . n1($y) . '" stroke="#e7edf3" stroke-width="1"/>';
        $svg .= '<text x="' . ($padL - 8) . '" y="' . n1($y + 4) . '" text-anchor="end" fill="#94a3b8" font-size="11">' . e(fmt_axis($val, $fmtL)) . '</text>';
        if ($showRight) {
            $valR = $maxR * ($i / $ticks);
            $svg .= '<text x="' . ($w - $padR + 8) . '" y="' . n1($y + 4) . '" text-anchor="start" fill="#94a3b8" font-size="11">' . e(fmt_axis($valR, $fmtR)) . '</text>';
        }
    }

    foreach ($series as $s) {
        if (empty($s['fill'])) {
            continue;
        }
        $axis = $s['axis'] ?? 'left';
        $max = $axis === 'right' ? $maxR : $maxL;
        $base = $yOf(0, $max);
        $d = '';
        $firstX = null;
        $lastX = null;
        foreach (array_values($s['data']) as $i => $v) {
            $px = $x((int) $i);
            $py = $yOf((float) $v, $max);
            $firstX ??= $px;
            $lastX = $px;
            $d .= ($i === 0 ? 'M' : 'L') . n1($px) . ' ' . n1($py) . ' ';
        }
        if ($d !== '' && $firstX !== null && $lastX !== null) {
            $svg .= '<path d="' . $d . 'L' . n1($lastX) . ' ' . n1($base) . ' L' . n1($firstX) . ' ' . n1($base) . ' Z" fill="' . e($s['color']) . '" opacity="0.16"/>';
        }
    }

    foreach ($series as $s) {
        $axis = $s['axis'] ?? 'left';
        $max = $axis === 'right' ? $maxR : $maxL;
        $fmt = $axis === 'right' ? $fmtR : $fmtL;
        $cmds = [];
        $dots = '';
        foreach (array_values($s['data']) as $i => $v) {
            $px = $x((int) $i);
            $py = $yOf((float) $v, $max);
            $cmds[] = ($i === 0 ? 'M' : 'L') . n1($px) . ' ' . n1($py);
            if ((float) $v > 0 && empty($s['hideDots'])) {
                $dots .= '<circle cx="' . n1($px) . '" cy="' . n1($py) . '" r="3.3" fill="#fff" stroke="' . e($s['color']) . '" stroke-width="2"><title>'
                    . e($s['name'] . ': ' . fmt_axis((float) $v, $fmt)) . '</title></circle>';
            }
        }
        $dash = !empty($s['dash']) ? ' stroke-dasharray="' . e((string) $s['dash']) . '"' : '';
        $width = (string) ($s['width'] ?? 2.2);
        $svg .= '<path d="' . implode(' ', $cmds) . '" fill="none" stroke="' . e($s['color']) . '" stroke-width="' . $width . '" stroke-linejoin="round" stroke-linecap="round"' . $dash . '/>';
        $svg .= $dots;
    }

    foreach ($labels as $i => $lab) {
        $svg .= '<text x="' . n1($x((int) $i)) . '" y="' . ($h - 8) . '" text-anchor="middle" fill="#94a3b8" font-size="11">' . e((string) $lab) . '</text>';
    }
    $svg .= '</svg>';

    $legend = '<div class="legend">';
    foreach ($series as $s) {
        $legend .= '<span><i style="background:' . e($s['color']) . '"></i>' . e($s['name']) . '</span>';
    }
    $legend .= '</div>';
    return $svg . $legend;
}

function svg_bar_chart(array $labels, array $series, array $opt = []): string
{
    $w = 560;
    $h = 240;
    $padL = 54;
    $padR = 12;
    $padT = 14;
    $padB = 30;
    $iw = $w - $padL - $padR;
    $ih = $h - $padT - $padB;
    $count = max(count($labels), 1);
    $max = 0.0;
    foreach ($series as $s) {
        foreach ($s['data'] as $v) {
            $max = max($max, (float) $v);
        }
    }
    $max = nice_ceil($max * 1.1);
    $ticks = 4;
    $groupW = $iw / $count;
    $nSeries = max(count($series), 1);
    $barW = min(16, ($groupW * 0.62) / $nSeries);

    $yOf = static function (float $v) use ($padT, $ih, $max): float {
        $plot = $ih - 6;
        return $padT + 4 + $plot * (1 - (max(0, $v) / $max));
    };
    $base = $yOf(0);

    $svg = '<svg class="chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . e($opt['label'] ?? 'Gráfico de barras') . '">';
    for ($i = 0; $i <= $ticks; $i++) {
        $val = $max * ($i / $ticks);
        $y = $yOf($val);
        $svg .= '<line x1="' . $padL . '" y1="' . n1($y) . '" x2="' . ($w - $padR) . '" y2="' . n1($y) . '" stroke="#e7edf3"/>';
        $svg .= '<text x="' . ($padL - 8) . '" y="' . n1($y + 4) . '" text-anchor="end" fill="#94a3b8" font-size="11">' . e(fmt_axis($val, 'money')) . '</text>';
    }
    foreach ($labels as $i => $lab) {
        $gx = $padL + ($i * $groupW);
        $used = $nSeries * $barW;
        $start = $gx + ($groupW - $used) / 2;
        foreach ($series as $si => $s) {
            $v = (float) ($s['data'][$i] ?? 0);
            $y = $yOf($v);
            $bh = max(0, $base - $y);
            $x = $start + ($si * $barW);
            if ($bh > 0) {
                $svg .= '<rect x="' . n1($x) . '" y="' . n1($y) . '" width="' . n1($barW - 3) . '" height="' . n1($bh) . '" rx="3" fill="' . e($s['color']) . '"><title>'
                    . e($s['name'] . ' · ' . $lab . ': ' . fmt_axis($v, 'money')) . '</title></rect>';
            }
        }
        $svg .= '<text x="' . n1($gx + $groupW / 2) . '" y="' . ($h - 8) . '" text-anchor="middle" fill="#94a3b8" font-size="11">' . e((string) $lab) . '</text>';
    }
    $svg .= '</svg>';
    $legend = '<div class="legend">';
    foreach ($series as $s) {
        $legend .= '<span><i style="background:' . e($s['color']) . '"></i>' . e($s['name']) . '</span>';
    }
    $legend .= '</div>';
    return $svg . $legend;
}

function sparkline(array $data, string $color = '#f97316'): string
{
    $vals = array_map('floatval', array_values($data));
    if ($vals === []) {
        $vals = [0.0];
    }
    $w = 320;
    $h = 36;
    $n = count($vals);
    $max = max(1.0, max($vals));
    $pts = [];
    foreach ($vals as $i => $v) {
        $x = $n === 1 ? $w / 2 : ($w * $i / ($n - 1));
        $y = 4 + ($h - 8) * (1 - ($v / $max));
        $pts[] = n1($x) . ',' . n1($y);
    }
    return '<svg class="spark" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" aria-hidden="true"><polyline fill="none" stroke="'
        . e($color) . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="' . implode(' ', $pts) . '"/></svg>';
}
