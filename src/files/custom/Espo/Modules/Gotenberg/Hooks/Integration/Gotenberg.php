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

namespace Espo\Modules\Gotenberg\Hooks\Integration;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Entities\Integration;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * @implements AfterSave<Integration>
 */
class Gotenberg implements AfterSave
{
    private const PDF_ENGINE_DEFAULT = 'Dompdf';

    private const PDF_ENGINE_GOTENBERG = 'Gotenberg';

    public function __construct(
        private readonly Config $config,
        private readonly ConfigWriter $configWriter
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->getId() !== self::PDF_ENGINE_GOTENBERG) {
            return;
        }

        $pdfEngineRevert = self::PDF_ENGINE_DEFAULT;
        $pdfEngine = $this->config->get('pdfEngine');

        if (is_string($pdfEngine) && $pdfEngine !== self::PDF_ENGINE_GOTENBERG) {
            $pdfEngineRevert = $pdfEngine;
        }

        if (!$entity->isEnabled()) {
            $this->configWriter->set('pdfEngine', $pdfEngineRevert);

            $this->configWriter->set('gotenbergPdfEngineRevert', null);
            $this->configWriter->set('gotenbergApiUrl', null);

            $this->configWriter->save();

            return;
        }

        $this->configWriter->set('pdfEngine', self::PDF_ENGINE_GOTENBERG);
        $this->configWriter->set('gotenbergPdfEngineRevert', $pdfEngineRevert);
        $this->configWriter->set('gotenbergApiUrl', $entity->get('gotenbergApiUrl'));

        $this->configWriter->save();
    }
}
