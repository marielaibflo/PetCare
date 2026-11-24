<?php

namespace App\Support;

/**
 * Generador de libros de Excel (.xlsx) sin librerías externas.
 * Un .xlsx es un ZIP con archivos XML: aquí se escriben las hojas con estilos
 * (título, encabezado morado, montos en Bs, fechas, bordes), filtros, paneles fijos y anchos de columna.
 *
 *   $x = new Excel('PetCare');
 *   $x->hoja('Propietarios', 'Lista de propietarios', ['Nombre', 'Correo'], $filas, ['monto' => [3]]);
 *   return $x->descargar('reporte.xlsx');
 */
class Excel
{
    // Índices de estilo (cellXfs). Los «_Z» son las filas alternadas (cebra).
    private const NORMAL = 0, TITULO = 1, SUB = 2, CAB = 3, TEXTO = 4, MONTO = 5, ENTERO = 6, FECHA = 7, TOTAL_T = 8, TOTAL_M = 9, PORC = 10, DECIMAL = 11,
        TEXTO_Z = 12, MONTO_Z = 13, ENTERO_Z = 14, FECHA_Z = 15, PORC_Z = 16, DECIMAL_Z = 17, LINK = 18, GRUPO_T = 19, GRUPO_M = 20, IDX_N = 21;

    private const CEBRA = [self::TEXTO => self::TEXTO_Z, self::MONTO => self::MONTO_Z, self::ENTERO => self::ENTERO_Z, self::FECHA => self::FECHA_Z,
        self::PORC => self::PORC_Z, self::DECIMAL => self::DECIMAL_Z];

    private array $hojas = [];

    private bool $conIndice = false;

    public function __construct(private string $autor = 'PetCare') {}

    /**
     * @param  array  $filas  filas de valores (string, int, float, null o \DateTimeInterface)
     * @param  array  $opc  ['monto' => [índices], 'porcentaje' => [...], 'total' => true, 'anchos' => [...],
     *                      'grupo' => índice de columna o fn(array $fila): string — agrupa filas que se abren y cierran con [+]/[−]]
     */
    public function hoja(string $nombre, string $titulo, array $cabeceras, array $filas, array $opc = [], ?string $subtitulo = null): static
    {
        $nombre = mb_substr(preg_replace('/[\\\\\/?*\[\]:]/u', ' ', $nombre), 0, 31);
        $this->hojas[] = compact('nombre', 'titulo', 'cabeceras', 'filas', 'opc', 'subtitulo');

        return $this;
    }

    /** Agrega al inicio una hoja «Índice» con un enlace a cada hoja (y en cada hoja, un enlace para volver). */
    public function conIndice(): static
    {
        $this->conIndice = true;

        return $this;
    }

    public function contenido(): string
    {
        $hojas = $this->hojas;
        if ($this->conIndice) {
            array_unshift($hojas, ['nombre' => 'Índice', 'indice' => true]);
        }
        $xmlHojas = [];
        foreach ($hojas as $i => &$h) {
            $xmlHojas['xl/worksheets/sheet'.($i + 1).'.xml'] = isset($h['indice']) ? $this->indiceXml() : $this->hojaXml($h);
        }
        unset($h);
        $this->hojasFinal = $hojas;
        $archivos = [
            '[Content_Types].xml' => $this->tipos(),
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>',
            'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
                .'<dc:title>Reportes PetCare</dc:title><dc:creator>'.$this->x($this->autor).'</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">'.gmdate('Y-m-d\TH:i:s\Z').'</dcterms:created></cp:coreProperties>',
            'xl/workbook.xml' => $this->libro(),
            'xl/_rels/workbook.xml.rels' => $this->relaciones(),
            'xl/styles.xml' => $this->estilos(),
        ];

        return self::zip($archivos + $xmlHojas);
    }

    private array $hojasFinal = [];

    public function descargar(string $nombre)
    {
        $data = $this->contenido();

        return response($data, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
            'Content-Length' => strlen($data),
        ]);
    }

    // ------------------------------------------------------------------ XML

    private function ref(string $hoja): string
    {
        return "'".str_replace("'", "''", $hoja)."'!A1";
    }

    private function indiceXml(): string
    {
        $r = 1;
        $filas = [$this->fila($r++, [['Reportes PetCare · Índice', self::TITULO]], 30),
            $this->fila($r++, [['Haz clic en el nombre de una hoja para ir a ella. En cada hoja, «← Volver al índice» te trae de regreso.', self::SUB]], 20)];
        $r++;
        $filas[] = $this->fila($r++, [['N.º', self::CAB], ['Hoja', self::CAB], ['Contenido', self::CAB], ['Registros', self::CAB]], 30);
        $links = '';
        foreach ($this->hojas as $i => $h) {
            $z = $i % 2 === 1;
            $filas[] = $this->fila($r, [[$i + 1, self::IDX_N], ['➜ '.$h['nombre'], self::LINK], [$h['titulo'], $z ? self::TEXTO_Z : self::TEXTO], [count($h['filas']), $z ? self::ENTERO_Z : self::ENTERO]], 24);
            $links .= '<hyperlink ref="B'.$r.'" location="'.$this->x($this->ref($h['nombre'])).'" display="'.$this->x($h['nombre']).'"/>';
            $r++;
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetPr><tabColor rgb="FF5B3FD6"/><pageSetUpPr fitToPage="1"/></sheetPr><sheetViews><sheetView workbookViewId="0" showGridLines="0" tabSelected="1"/></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="20"/><cols><col min="1" max="1" width="8" customWidth="1"/><col min="2" max="2" width="30" customWidth="1"/><col min="3" max="3" width="62" customWidth="1"/><col min="4" max="4" width="14" customWidth="1"/></cols>'
            .'<sheetData>'.implode('', $filas).'</sheetData>'
            .'<mergeCells count="2"><mergeCell ref="A1:D1"/><mergeCell ref="A2:D2"/></mergeCells>'
            .'<hyperlinks>'.$links.'</hyperlinks>'
            .'<pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/><pageSetup orientation="portrait" paperSize="1" fitToWidth="1" fitToHeight="0"/>'
            .'</worksheet>';
    }

    private function hojaXml(array $h): string
    {
        $n = max(1, count($h['cabeceras']));
        $montos = $h['opc']['monto'] ?? [];
        $porc = $h['opc']['porcentaje'] ?? [];
        $grupo = $h['opc']['grupo'] ?? null;
        $filas = array_values($h['filas']);
        $clave = $grupo === null ? null : (is_callable($grupo) ? $grupo : fn ($f) => (string) (array_values($f)[$grupo] ?? ''));
        if ($clave) {
            // Agrupa conservando el orden de aparición de cada grupo y el orden de las filas dentro del grupo
            $orden = [];
            foreach ($filas as $k => $f) {
                $orden[$clave($f)] ??= count($orden);
            }
            $idx = array_keys($filas);
            usort($idx, fn ($a, $b) => [$orden[$clave($filas[$a])], $a] <=> [$orden[$clave($filas[$b])], $b]);
            $filas = array_map(fn ($k) => $filas[$k], $idx);
        }
        $filasXml = [];
        $r = 1;
        $filasXml[] = $this->fila($r++, [[$h['titulo'], self::TITULO]], 32);
        $filasXml[] = $this->fila($r++, [[$h['subtitulo'] ?? ('Generado el '.now()->format('d/m/Y H:i').' · PetCare'), self::SUB]], 20);
        $volver = $this->conIndice;
        $filasXml[] = $volver ? $this->fila($r, [['← Volver al índice', self::LINK]], 22) : '';
        $r++;
        $cab = $r;
        $filasXml[] = $this->fila($r++, array_map(fn ($c) => [$c, self::CAB], $h['cabeceras']), 36);
        $primera = $r;
        $actual = null;
        $zebra = 0;
        $grupos = [];
        foreach ($filas as $f) {
            if ($clave && ($k = $clave($f)) !== $actual) {
                // Fila de encabezado del grupo: se completa con los subtotales al cerrarlo
                $actual = $k;
                $grupos[] = ['fila' => $r, 'titulo' => $k, 'desde' => $r + 1, 'n' => 0];
                $filasXml[] = '%%GRUPO'.(count($grupos) - 1).'%%';
                $r++;
                $zebra = 0;
            }
            $celdas = [];
            foreach (array_values($f) as $i => $v) {
                $estilo = match (true) {
                    in_array($i, $montos, true) => self::MONTO,
                    in_array($i, $porc, true) => self::PORC,
                    $v instanceof \DateTimeInterface => self::FECHA,
                    is_int($v) => self::ENTERO,
                    is_float($v) => self::DECIMAL,
                    default => self::TEXTO,
                };
                $celdas[] = [$v, $zebra % 2 ? self::CEBRA[$estilo] : $estilo];
            }
            $filasXml[] = $this->fila($r++, $celdas, null, $clave ? 1 : 0);
            if ($clave) {
                $grupos[count($grupos) - 1]['n']++;
            }
            $zebra++;
        }
        $ultima = $r - 1;
        // Si la segunda columna lleva subtotal, el texto del grupo no puede pasar a ella: se omite la cantidad de filas
        $angosto = in_array(1, $montos, true);
        foreach ($grupos as $g => $gr) {
            $hasta = $gr['desde'] + $gr['n'] - 1;
            $celdas = [['▾ '.$gr['titulo'].($angosto ? '' : '  ('.$gr['n'].' '.($gr['n'] === 1 ? 'fila' : 'filas').')'), self::GRUPO_T]];
            for ($i = 1; $i < $n; $i++) {
                $col = self::col($i);
                $celdas[] = in_array($i, $montos, true) ? ['=SUBTOTAL(9,'.$col.$gr['desde'].':'.$col.$hasta.')', self::GRUPO_M] : ['', self::GRUPO_T];
            }
            $filasXml[array_search('%%GRUPO'.$g.'%%', $filasXml, true)] = $this->fila($gr['fila'], $celdas, 24);
        }
        if (($h['opc']['total'] ?? false) && $ultima >= $primera) {
            $celdas = [['TOTAL', self::TOTAL_T]];
            for ($i = 1; $i < $n; $i++) {
                $col = self::col($i);
                $celdas[] = in_array($i, $montos, true) ? ['=SUBTOTAL(9,'.$col.$primera.':'.$col.$ultima.')', self::TOTAL_M] : ['', self::TOTAL_T];
            }
            $filasXml[] = $this->fila($r++, $celdas, 26);
        }

        // Anchos: según el texto más largo de cada columna (letra de 12 pt), con límites
        $anchos = $h['opc']['anchos'] ?? [];
        $cols = '';
        for ($i = 0; $i < $n; $i++) {
            $w = isset($anchos[$i]) ? round($anchos[$i] * 1.2) : null;
            if (! $w) {
                $largo = mb_strlen((string) $h['cabeceras'][$i]);
                foreach (array_slice($filas, 0, 300) as $f) {
                    $v = array_values($f)[$i] ?? '';
                    $largo = max($largo, mb_strlen($v instanceof \DateTimeInterface ? '00/00/0000' : (is_float($v) ? 'Bs '.number_format($v, 2) : (string) $v)));
                }
                $w = min(max(round($largo * 1.2) + 4, 12), 70);
            }
            $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$w.'" customWidth="1"/>';
        }
        $ref = 'A'.$cab.':'.self::col($n - 1).max($cab, $ultima);
        $fin = self::col(max($n - 1, 3));

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetPr>'.($clave ? '<outlinePr summaryBelow="0"/>' : '').'<pageSetUpPr fitToPage="1"/></sheetPr>'
            .'<sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane ySplit="'.$cab.'" topLeftCell="A'.($cab + 1).'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="20"'.($clave ? ' outlineLevelRow="1"' : '').'/><cols>'.$cols.'</cols>'
            .'<sheetData>'.implode('', $filasXml).'</sheetData>'
            .($ultima >= $primera ? '<autoFilter ref="'.$ref.'"/>' : '')
            .'<mergeCells count="2"><mergeCell ref="A1:'.$fin.'1"/><mergeCell ref="A2:'.$fin.'2"/></mergeCells>'
            .($volver ? '<hyperlinks><hyperlink ref="A3" location="'.$this->x($this->ref('Índice')).'" display="Volver al índice"/></hyperlinks>' : '')
            .'<printOptions horizontalCentered="1"/><pageMargins left="0.4" right="0.4" top="0.5" bottom="0.6" header="0.3" footer="0.3"/>'
            .'<pageSetup orientation="landscape" paperSize="1" fitToWidth="1" fitToHeight="0"/>'
            .'<headerFooter><oddFooter>&amp;L'.$this->x($h['nombre']).' · PetCare&amp;RPágina &amp;P de &amp;N</oddFooter></headerFooter>'
            .'</worksheet>';
    }

    private function fila(int $r, array $celdas, ?int $alto = null, int $nivel = 0): string
    {
        $xml = '<row r="'.$r.'"'.($alto ? ' ht="'.$alto.'" customHeight="1"' : '').($nivel ? ' outlineLevel="'.$nivel.'"' : '').'>';
        foreach ($celdas as $i => [$v, $s]) {
            $ref = self::col($i).$r;
            if ($v === null || $v === '') {
                $xml .= '<c r="'.$ref.'" s="'.$s.'"/>';
            } elseif (is_string($v) && str_starts_with($v, '=')) {
                $xml .= '<c r="'.$ref.'" s="'.$s.'"><f>'.$this->x(substr($v, 1)).'</f></c>';
            } elseif ($v instanceof \DateTimeInterface) {
                $serial = ($v->getTimestamp() + $v->getOffset()) / 86400 + 25569;
                $xml .= '<c r="'.$ref.'" s="'.$s.'"><v>'.floor($serial).'</v></c>';
            } elseif (is_int($v) || is_float($v)) {
                $xml .= '<c r="'.$ref.'" s="'.$s.'"><v>'.$v.'</v></c>';
            } elseif (is_bool($v)) {
                $xml .= '<c r="'.$ref.'" s="'.$s.'" t="inlineStr"><is><t>'.($v ? 'Sí' : 'No').'</t></is></c>';
            } else {
                $xml .= '<c r="'.$ref.'" s="'.$s.'" t="inlineStr"><is><t xml:space="preserve">'.$this->x((string) $v).'</t></is></c>';
            }
        }

        return $xml.'</row>';
    }

    private function libro(): string
    {
        $hojas = '';
        $nombres = '';
        foreach ($this->hojasFinal as $i => $h) {
            $hojas .= '<sheet name="'.$this->x($h['nombre']).'" sheetId="'.($i + 1).'" r:id="rId'.($i + 1).'"/>';
            if (isset($h['indice']) || ! count($h['filas'])) {
                continue;
            }
            $n = max(1, count($h['cabeceras']));
            $q = $this->x(str_replace("'", "''", $h['nombre']));
            $nombres .= '<definedName name="_xlnm._FilterDatabase" localSheetId="'.$i.'" hidden="1">\''.$q.'\'!$A$4:$'.self::col($n - 1).'$'.(4 + count($h['filas']) + 200).'</definedName>';
            $nombres .= '<definedName name="_xlnm.Print_Titles" localSheetId="'.$i.'">\''.$q.'\'!$4:$4</definedName>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<bookViews><workbookView activeTab="0"/></bookViews><sheets>'.$hojas.'</sheets>'.($nombres ? '<definedNames>'.$nombres.'</definedNames>' : '').'</workbook>';
    }

    private function relaciones(): string
    {
        $r = '';
        foreach ($this->hojasFinal as $i => $h) {
            $r .= '<Relationship Id="rId'.($i + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.($i + 1).'.xml"/>';
        }
        $r .= '<Relationship Id="rId'.(count($this->hojasFinal) + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$r.'</Relationships>';
    }

    private function tipos(): string
    {
        $h = '';
        foreach ($this->hojasFinal as $i => $x) {
            $h .= '<Override PartName="/xl/worksheets/sheet'.($i + 1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'.$h.'</Types>';
    }

    /** Letra de 12 pt en los datos, 13 pt en cabeceras y 16 pt en el título; filas alternadas en gris muy claro. */
    private function estilos(): string
    {
        $b = '<border><left style="thin"><color rgb="FFD9D5E8"/></left><right style="thin"><color rgb="FFD9D5E8"/></right><top style="thin"><color rgb="FFD9D5E8"/></top><bottom style="thin"><color rgb="FFD9D5E8"/></bottom><diagonal/></border>';
        $dato = fn (int $fmt, int $fill, string $alin) => '<xf numFmtId="'.$fmt.'" fontId="0" fillId="'.$fill.'" borderId="1" xfId="0" applyNumberFormat="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment '.$alin.' vertical="center" wrapText="1"/></xf>';
        $tipos = [[0, ''], [164, 'horizontal="right"'], [1, 'horizontal="center"'], [165, 'horizontal="center"'], [166, 'horizontal="center"'], [4, 'horizontal="right"']];

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="3"><numFmt numFmtId="164" formatCode="&quot;Bs&quot; #,##0.00"/><numFmt numFmtId="165" formatCode="dd/mm/yyyy"/><numFmt numFmtId="166" formatCode="0&quot; %&quot;"/></numFmts>'
            .'<fonts count="7"><font><sz val="12"/><name val="Arial"/></font><font><b/><sz val="16"/><color rgb="FF5B3FD6"/><name val="Arial"/></font>'
            .'<font><i/><sz val="10"/><color rgb="FF6B6880"/><name val="Arial"/></font><font><b/><sz val="13"/><color rgb="FFFFFFFF"/><name val="Arial"/></font><font><b/><sz val="12"/><name val="Arial"/></font>'
            .'<font><u/><sz val="12"/><color rgb="FF2463C9"/><name val="Arial"/></font><font><b/><sz val="12"/><color rgb="FF4429B5"/><name val="Arial"/></font></fonts>'
            .'<fills count="6"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF5B3FD6"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFEDE9FE"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFF6F5FA"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFF3F0FF"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'.$b.'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="22">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'                                                                     // 0 normal
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment vertical="center"/></xf>'                     // 1 título
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment vertical="center"/></xf>'                     // 2 subtítulo
            .'<xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>' // 3 cabecera
            .$dato(0, 0, '').$dato(164, 0, 'horizontal="right"').$dato(1, 0, 'horizontal="center"').$dato(165, 0, 'horizontal="center"')          // 4–7
            .'<xf numFmtId="0" fontId="4" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment vertical="center"/></xf>' // 8 total texto
            .'<xf numFmtId="164" fontId="4" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="right" vertical="center"/></xf>' // 9 total monto
            .$dato(166, 0, 'horizontal="center"').$dato(4, 0, 'horizontal="right"')                                                                // 10–11
            .implode('', array_map(fn ($t) => $dato($t[0], 4, $t[1]), $tipos))                                                                     // 12–17 cebra
            .'<xf numFmtId="0" fontId="5" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment vertical="center"/></xf>'                      // 18 enlace
            .'<xf numFmtId="0" fontId="6" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment vertical="center"/></xf>' // 19 grupo
            .'<xf numFmtId="164" fontId="6" fillId="5" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="right" vertical="center"/></xf>' // 20 grupo monto
            .'<xf numFmtId="1" fontId="4" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' // 21 n.º índice
            .'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    // ------------------------------------------------------------------ utilidades

    private function x(string $s): string
    {
        // Quita caracteres de control no válidos en XML
        return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    public static function col(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26).$s;
        }

        return $s;
    }

    /** ZIP con compresión deflate (si existe zlib) escrito a mano: no depende de la extensión zip de PHP. */
    public static function zip(array $archivos): string
    {
        $datos = '';
        $central = '';
        $n = 0;
        $t = getdate();
        $hora = ($t['hours'] << 11) | ($t['minutes'] << 5) | intdiv($t['seconds'], 2);
        $fecha = (($t['year'] - 1980) << 9) | ($t['mon'] << 5) | $t['mday'];
        foreach ($archivos as $nombre => $contenido) {
            $crc = crc32($contenido);
            $metodo = function_exists('gzdeflate') ? 8 : 0;
            $comp = $metodo ? gzdeflate($contenido, 6) : $contenido;
            $cab = pack('vvvvvVVVvv', 20, 0x0800, $metodo, $hora, $fecha, $crc, strlen($comp), strlen($contenido), strlen($nombre), 0);
            $offset = strlen($datos);
            $datos .= "PK\x03\x04".$cab.$nombre.$comp;
            $central .= "PK\x01\x02".pack('vvvvvvVVVvvvvvVV', 20, 20, 0x0800, $metodo, $hora, $fecha, $crc, strlen($comp), strlen($contenido), strlen($nombre), 0, 0, 0, 0, 0, $offset).$nombre;
            $n++;
        }

        return $datos.$central."PK\x05\x06".pack('vvvvVVv', 0, 0, $n, $n, strlen($central), strlen($datos), 0);
    }
}
