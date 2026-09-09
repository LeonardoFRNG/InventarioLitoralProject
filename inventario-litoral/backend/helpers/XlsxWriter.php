<?php
/**
 * Generador de archivos .xlsx nativo, sin dependencias externas
 * (no requiere Composer/PhpSpreadsheet). Un XLSX es un ZIP de XML,
 * así que lo construimos directamente con ZipArchive.
 *
 * Soporta múltiples hojas, encabezado en negrita con relleno de color,
 * anchos de columna personalizados y congelar la fila de encabezado.
 */
class XlsxWriter
{
    private array $sheets = [];

    /**
     * @param string $nombre       Nombre de la hoja (máx 31 caracteres)
     * @param array  $encabezados  ['Código', 'Nombre', 'Estado', ...]
     * @param array  $filas        [['INV-001','Laptop HP','Activo'], ...]
     * @param array  $anchosCol    [15, 30, 12, ...] ancho aproximado por columna
     */
    public function agregarHoja(string $nombre, array $encabezados, array $filas, array $anchosCol = []): void
    {
        $this->sheets[] = [
            'nombre'      => substr($nombre, 0, 31),
            'encabezados' => $encabezados,
            'filas'       => $filas,
            'anchos'      => $anchosCol,
        ];
    }

    public function guardar(string $rutaArchivo): void
    {
        if (file_exists($rutaArchivo)) {
            unlink($rutaArchivo);
        }

        $zip = new ZipArchive();
        $zip->open($rutaArchivo, ZipArchive::CREATE);

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->relsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());

        foreach ($this->sheets as $index => $sheet) {
            $numero = $index + 1;
            $zip->addFromString("xl/worksheets/sheet{$numero}.xml", $this->sheetXml($sheet));
        }

        $zip->close();
    }

    private function contentTypesXml(): string
    {
        $sheetsXml = '';
        foreach ($this->sheets as $index => $sheet) {
            $numero = $index + 1;
            $sheetsXml .= "<Override PartName=\"/xl/worksheets/sheet{$numero}.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>";
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $sheetsXml
            . '</Types>';
    }

    private function relsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbookXml(): string
    {
        $sheetsXml = '';
        foreach ($this->sheets as $index => $sheet) {
            $numero = $index + 1;
            $nombre = htmlspecialchars($sheet['nombre'], ENT_XML1);
            $sheetsXml .= "<sheet name=\"{$nombre}\" sheetId=\"{$numero}\" r:id=\"rId{$numero}\"/>";
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . "<sheets>{$sheetsXml}</sheets>"
            . '</workbook>';
    }

    private function workbookRelsXml(): string
    {
        $relsXml = '';
        foreach ($this->sheets as $index => $sheet) {
            $numero = $index + 1;
            $relsXml .= "<Relationship Id=\"rId{$numero}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet{$numero}.xml\"/>";
        }
        $relsXml .= '<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $relsXml
            . '</Relationships>';
    }

    private function stylesXml(): string
    {
        // xf index 0 = normal, xf index 1 = encabezado (negrita + relleno morado + texto blanco)
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><sz val="11"/><name val="Calibri"/><b/><color rgb="FFFFFFFF"/></font>'
            . '</fonts>'
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF4C1D95"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            . '</cellXfs>'
            . '</styleSheet>';
    }

    private function sheetXml(array $sheet): string
    {
        $filasXml = '';
        $numFila = 1;

        // Fila de encabezado (estilo s="1" = negrita con relleno)
        $filasXml .= $this->filaXml($numFila, $sheet['encabezados'], true);
        $numFila++;

        foreach ($sheet['filas'] as $fila) {
            $filasXml .= $this->filaXml($numFila, $fila, false);
            $numFila++;
        }

        $colsXml = '';
        if (!empty($sheet['anchos'])) {
            $colsXml = '<cols>';
            foreach ($sheet['anchos'] as $i => $ancho) {
                $col = $i + 1;
                $colsXml .= "<col min=\"{$col}\" max=\"{$col}\" width=\"{$ancho}\" customWidth=\"1\"/>";
            }
            $colsXml .= '</cols>';
        }

        $totalCols = max(count($sheet['encabezados']), 1);
        $ultimaCol = $this->numeroALetra($totalCols);
        $ultimaFila = $numFila - 1;

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . "<dimension ref=\"A1:{$ultimaCol}{$ultimaFila}\"/>"
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . $colsXml
            . "<sheetData>{$filasXml}</sheetData>"
            . '</worksheet>';
    }

    private function filaXml(int $numFila, array $valores, bool $esEncabezado): string
    {
        $celdas = '';
        foreach (array_values($valores) as $i => $valor) {
            $col = $this->numeroALetra($i + 1);
            $ref = "{$col}{$numFila}";
            $estilo = $esEncabezado ? ' s="1"' : '';

            if (is_numeric($valor) && !$esEncabezado && $valor !== '') {
                $celdas .= "<c r=\"{$ref}\"{$estilo}><v>" . htmlspecialchars((string)$valor, ENT_XML1) . '</v></c>';
            } else {
                $texto = htmlspecialchars((string)$valor, ENT_XML1);
                $celdas .= "<c r=\"{$ref}\" t=\"inlineStr\"{$estilo}><is><t xml:space=\"preserve\">{$texto}</t></is></c>";
            }
        }
        return "<row r=\"{$numFila}\">{$celdas}</row>";
    }

    private function numeroALetra(int $numero): string
    {
        $letra = '';
        while ($numero > 0) {
            $modulo = ($numero - 1) % 26;
            $letra = chr(65 + $modulo) . $letra;
            $numero = intdiv($numero - $modulo, 26);
        }
        return $letra;
    }
}
