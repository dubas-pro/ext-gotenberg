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

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Espo\Core\Htmlizer\TemplateRendererFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Log;
use Espo\Core\Utils\Metadata;
use Espo\ORM\Entity;
use Espo\Tools\Pdf\Data;
use Espo\Tools\Pdf\Dompdf\ImageSourceProvider;
use Espo\Tools\Pdf\Params;
use Espo\Tools\Pdf\Template;
use Picqer\Barcode\BarcodeGeneratorSVG;

class HtmlComposer
{
    private string $defaultFontFace = 'DejaVu Sans';

    public function __construct(
        private readonly Config $config,
        private readonly Metadata $metadata,
        private readonly TemplateRendererFactory $templateRendererFactory,
        private readonly ImageSourceProvider $imageSourceProvider,
        private readonly Log $log
    ) {}

    public function composeMain(Template $template, Entity $entity, Params $params, Data $data): string
    {
        $renderer = $this->templateRendererFactory
            ->create()
            ->setApplyAcl($params->applyAcl())
            ->setEntity($entity)
            ->setSkipInlineAttachmentHandling()
            ->setData($data->getAdditionalTemplateData());

        $html = $renderer->renderTemplate($template->getBody());
        $html = $this->replaceTags($html);

        if (preg_match('/^<!DOCTYPE html>|<html>/', ltrim($html))) {
            return $html;
        }

        return $this->composeHtmlDocument($template, $entity, sprintf('<main>%s</main>', $html));
    }

    public function composeHeader(Template $template, Entity $entity, Params $params, Data $data): ?string
    {
        if (!$template->hasHeader()) {
            return null;
        }

        $renderer = $this->templateRendererFactory
            ->create()
            ->setApplyAcl($params->applyAcl())
            ->setEntity($entity)
            ->setSkipInlineAttachmentHandling()
            ->setData($data->getAdditionalTemplateData());

        $html = $renderer->renderTemplate($template->getHeader());
        $html = $this->replaceHeadTags($html);

        if (preg_match('/^<!DOCTYPE html>|<html>/', ltrim($html))) {
            return $html;
        }

        return $this->composeHtmlDocument($template, $entity, sprintf('<header>%s</header>', $html));
    }

    public function composeFooter(Template $template, Entity $entity, Params $params, Data $data): ?string
    {
        if (!$template->hasFooter()) {
            return null;
        }

        $renderer = $this->templateRendererFactory
            ->create()
            ->setApplyAcl($params->applyAcl())
            ->setEntity($entity)
            ->setSkipInlineAttachmentHandling()
            ->setData($data->getAdditionalTemplateData());

        $html = $renderer->renderTemplate($template->getFooter());
        $html = $this->replaceHeadTags($html);

        if (preg_match('/^<!DOCTYPE html>|<html>/', ltrim($html))) {
            return $html;
        }

        return $this->composeHtmlDocument($template, $entity, sprintf('<footer>%s</footer>', $html));
    }

    private function composeHead(Template $template, Entity $entity): string
    {
        $topMargin = $template->getTopMargin();
        $rightMargin = $template->getRightMargin();
        $bottomMargin = $template->getBottomMargin();
        $leftMargin = $template->getLeftMargin();

        $headerPosition = $template->getHeaderPosition();
        $footerPosition = $template->getFooterPosition();

        $fontSize = $this->config->get('pdfFontSize') ?? null;
        if (!is_numeric($fontSize)) {
            $fontSize = 12;
        }

        $htmlTitle = '';
        if ($template->hasTitle()) {
            $htmlTitle = $this->replacePlaceholders($template->getTitle(), $entity);
            $htmlTitle = htmlspecialchars($htmlTitle);
        }

        $htmlHeadItemList = $this->metadata->get(['app', 'pdfEngines', 'Gotenberg', 'htmlHeadItemList']);
        if (!is_array($htmlHeadItemList)) {
            $htmlHeadItemList = [];
        }
        $htmlHeadItemList = implode(PHP_EOL, $htmlHeadItemList);

        $templateStyle = $template->getStyle() ?? '';

        /** @noinspection HtmlRequiredTitleElement */
        return "
            <head>
                <meta charset=\"utf-8\" />
                <title>{$htmlTitle}</title>
                {$htmlHeadItemList}
            </head>
            <style>

            html {
                line-height: 1.15; /* 1 */
                -webkit-text-size-adjust: 100%; /* 2 */
            }

            body {
                font-family: '{$this->getFontFace($template)}', sans-serif;
                font-size: {$fontSize}pt;
                margin: 0;
            }

            main {
                display: block;
            }

            header {
                position: fixed;
                margin-top: -{$topMargin}mm;
                margin-left: -{$rightMargin}mm;
                margin-right: -{$leftMargin}mm;
                top: {$headerPosition}mm;
                left: {$leftMargin}mm;
                right: {$rightMargin}mm;
            }

            footer {
                position: fixed;
                margin-bottom: -{$bottomMargin}mm;
                margin-left: -{$leftMargin}mm;
                margin-right: -{$rightMargin}mm;
                height: {$footerPosition}mm;
                bottom: 0;
                left: {$leftMargin}mm;
                right: {$rightMargin}mm;
            }

            {$templateStyle}
            </style>
        ";
    }

    private function composeHtmlDocument(Template $template, Entity $entity, string $html): string
    {
        return "
            <!DOCTYPE html>
            <html>
                {$this->composeHead($template, $entity)}
                <body>
                    {$html}
                </body>
            </html>
        ";
    }

    private function replaceTags(string $html): string
    {
        /** @noinspection HtmlUnknownAttribute */
        $html = str_replace('<br pagebreak="true">', '<div style="page-break-after: always;"></div>', $html);
        $html = preg_replace('/src="@([A-Za-z0-9+\/]*={0,2})"/', 'src="data:image/jpeg;base64,$1"', $html);
        $html = str_replace('?entryPoint=attachment&amp;', '?entryPoint=attachment&', $html ?? '');

        $html = preg_replace_callback(
            '/<barcodeimage data="([^"]+)"\/>/',
            function ($matches) {
                $dataString = $matches[1];

                $data = json_decode(urldecode($dataString), true);

                if (!is_array($data)) {
                    return '';
                }

                return $this->composeBarcode($data);
            },
            $html
        ) ?? '';

        return preg_replace_callback(
            "/src=\"\?entryPoint=attachment&id=([A-Za-z0-9]*)\"/",
            function ($matches) {
                $id = $matches[1];

                if (!$id) {
                    return '';
                }

                $src = $this->imageSourceProvider->get($id);

                if (!$src) {
                    return '';
                }

                return "src=\"$src\"";
            },
            $html
        ) ?? '';
    }

    private function replaceHeadTags(string $html): string
    {
        $html = str_replace('{date}', '<span class="date"></span>', $html);
        $html = str_replace('{title}', '<span class="title"></span>', $html);
        $html = str_replace('{url}', '<span class="url"></span>', $html);
        $html = str_replace('{pageNumber}', '<span class="pageNumber"></span>', $html);
        $html = str_replace('{totalPages}', '<span class="totalPages"></span>', $html);

        return $this->replaceTags($html);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function composeBarcode(array $data): string
    {
        $value = $data['value'] ?? null;

        if (!is_string($value)) {
            return '';
        }

        $codeType = $data['type'] ?? null;
        if (!is_string($codeType)) {
            $codeType = 'CODE128';
        }

        /** @noinspection SpellCheckingInspection */
        $typeMap = [
            "CODE128" => 'C128',
            "CODE128A" => 'C128A',
            "CODE128B" => 'C128B',
            "CODE128C" => 'C128C',
            "EAN13" => 'EAN13',
            "EAN8" => 'EAN8',
            "EAN5" => 'EAN5',
            "EAN2" => 'EAN2',
            "UPC" => 'UPCA',
            "UPCE" => 'UPCE',
            "ITF14" => 'I25',
            "pharmacode" => 'PHARMA',
            "QRcode" => 'QRCODE,H',
        ];

        $type = $typeMap[$codeType] ?? null;

        /** @noinspection SpellCheckingInspection */
        if ($codeType === 'QRcode') {
            $width = $data['width'] ?? null;
            if (!is_numeric($width)) {
                $width = 40;
            }

            $height = $data['height'] ?? null;
            if (!is_numeric($height)) {
                $height = 40;
            }

            $options = new QROptions();
            $options->outputType = QRCode::OUTPUT_MARKUP_SVG;
            $options->eccLevel = QRCode::ECC_H;

            $code = (new QRCode($options))->render($value);

            if (!is_string($code)) {
                $this->log->warning("Failed to generate QR code.");

                return '';
            }

            $css = "width: {$width}mm; height: {$height}mm;";

            /** @noinspection HtmlRequiredAltAttribute */
            return "<img src=\"$code\" style=\"$css\">";
        }

        if (!$type || $type === 'QRCODE,H') {
            $this->log->warning("Not supported barcode type $codeType.");

            return '';
        }

        $width = $data['width'] ?? null;
        if (!is_numeric($width)) {
            $width = 60;
        }

        $height = $data['height'] ?? null;
        if (!is_numeric($height)) {
            $height = 30;
        }

        $color = $data['color'] ?? null;
        if (!is_string($color)) {
            $color = '#000';
        }

        $code = (new BarcodeGeneratorSVG())->getBarcode($value, $type, 2, (float) $height, $color);

        $encoded = base64_encode($code);

        $css = "width: {$width}mm; height: {$height}mm;";

        /** @noinspection HtmlRequiredAltAttribute */
        return "<img src=\"data:image/svg+xml;base64,$encoded\" style=\"$css\">";
    }

    private function replacePlaceholders(string $string, Entity $entity): string
    {
        $newString = $string;

        $attributeList = ['name'];

        foreach ($attributeList as $attribute) {
            $value = (string) ($entity->get($attribute) ?? '');  // @phpstan-ignore-line

            $newString = str_replace('{$' . $attribute . '}', $value, $newString);
        }

        return $newString;
    }

    private function getFontFace(Template $template): string
    {
        if (is_string($template->getFontFace())) {
            return $template->getFontFace();
        }

        if (is_string($this->config->get('pdfFontFace'))) {
            return $this->config->get('pdfFontFace');
        }

        return $this->defaultFontFace;
    }
}
