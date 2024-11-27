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

use Espo\ORM\Entity;
use Espo\Tools\Pdf\Data;
use Espo\Tools\Pdf\EntityPrinter as EntityPrinterInterface;
use Espo\Tools\Pdf\Params;
use Espo\Tools\Pdf\Template;

class EntityPrinter implements EntityPrinterInterface
{
    public function __construct(
        private readonly GotenbergRenderer $gotenbergRenderer,
    ) {}

    public function print(Template $template, Entity $entity, Params $params, Data $data): Contents
    {
        return $this->gotenbergRenderer->renderPdf($template, $entity, $params, $data);
    }
}
