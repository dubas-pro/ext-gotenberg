<?php
/**
 * This file is part of the Gotenberg - EspoCRM extension.
 *
 * dubas s.c. - contact@dubas.pro
 * Copyright (C) 2024-2024 Arkadiy Asuratov, Emil Dubielecki
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Espo\Modules\Gotenberg\Engine;

use Espo\Core\Utils\Config;
use Espo\Core\Utils\Log;
use Espo\Core\Utils\Metadata;
use Espo\ORM\Entity;
use Espo\Tools\Pdf\Data;
use Espo\Tools\Pdf\Params;
use Espo\Tools\Pdf\Template;
use Gotenberg\Exceptions\NoOutputFileInResponse;
use Gotenberg\Gotenberg;
use Gotenberg\Modules\ChromiumPdf;
use Gotenberg\Stream;
use RuntimeException;

class GotenbergRenderer
{
    public function __construct(
        private readonly Config $config,
        private readonly Metadata $metadata,
        private readonly Log $log,
        private readonly HtmlComposer $htmlComposer
    ) {}

    public function renderPdf(Template $template, Entity $entity, Params $params, Data $data): Contents
    {
        $pdf = $this->initialize($template);

        $header = $this->htmlComposer->composeHeader($template, $entity, $params, $data);
        $main = $this->htmlComposer->composeMain($template, $entity, $params, $data);
        $footer = $this->htmlComposer->composeFooter($template, $entity, $params, $data);

        if (is_string($header)) {
            $pdf->header(Stream::string('header.html', $header));
        }

        if (is_string($footer)) {
            $pdf->footer(Stream::string('footer.html', $footer));
        }

        $response = Gotenberg::send(
            request: $pdf->html(Stream::string('index.html', $main)),
        );

        $contentDisposition = $response->getHeader('Content-Disposition');
        if (count($contentDisposition) === 0) {
            throw new NoOutputFileInResponse();
        }

        return new Contents($response);
    }

    private function initialize(Template $template): ChromiumPdf
    {
        $pdf = Gotenberg::chromium($this->getApiUrl())
            ->pdf();

        if ($template->getPageOrientation() === Template::PAGE_ORIENTATION_LANDSCAPE) {
            $pdf->landscape();
        }

        $this->setPaperSize($pdf, $template);

        $pdf->margins(
            top: $template->getTopMargin() . 'pt',
            bottom: $template->getBottomMargin() . 'pt',
            left: $template->getLeftMargin() . 'pt',
            right: $template->getRightMargin() . 'pt',
        );

        return $pdf;
    }

    private function getApiUrl(): string
    {
        $value = $this->config->get('gotenbergApiUrl');

        if (!is_string($value)) {
            throw new RuntimeException('Gotenberg API URL is not set.');
        }

        return $value;
    }

    private function setPaperSize(ChromiumPdf $pdf, Template $template): void
    {
        if ($template->getPageFormat() === 'Custom') {
            $pdf->paperSize(
                width: $template->getPageWidth() . 'mm',
                height: $template->getPageHeight() . 'mm',
            );

            return;
        }

        if ($template->getPageFormat() === 'Single Page') {
            $pdf->singlePage();

            return;
        }

        $paperSizeList = $this->metadata->get(['app', 'pdfEngines', 'Gotenberg', 'paperSizeList', $template->getPageFormat()]);

        if (!is_array($paperSizeList)) {
            throw new RuntimeException("Gotenberg: Paper size for '{$template->getPageFormat()}' is not set.");
        }

        $width = $paperSizeList['width'] ?? null;
        $height = $paperSizeList['height'] ?? null;
        $unit = $paperSizeList['unit'] ?? null;

        if (is_numeric($width)) {
            $width = (float) $width;
        }

        if (is_numeric($height)) {
            $height = (float) $height;
        }

        if (!is_float($width) || !is_float($height)) {
            throw new RuntimeException("Gotenberg: Paper size for '{$template->getPageFormat()}' is not set correctly.");
        }

        if (!is_string($unit)) {
            $this->log->warning("Gotenberg: Paper size unit for '{$template->getPageFormat()}' is not a string. Using default unit 'mm'.");
            $unit = 'mm';
        }

        $pdf->paperSize(
            width: $template->getPageWidth() . 'mm',
            height: $template->getPageHeight() . 'mm',
        );
    }
}
