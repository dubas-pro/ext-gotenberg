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

use Espo\Tools\Pdf\Contents as ContentsInterface;
use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

class Contents implements ContentsInterface
{
    private ?string $string = null;

    public function __construct(
        private readonly ResponseInterface $response,
    ) {}

    public function getStream(): StreamInterface
    {
        $resource = fopen('php://temp', 'r+');

        if ($resource === false) {
            throw new RuntimeException("Could not open temp.");
        }

        fwrite($resource, $this->getString());
        rewind($resource);

        return new Stream($resource);
    }

    public function getString(): string
    {
        if ($this->string === null) {
            $this->string = $this->response->getBody()->getContents();
        }

        return $this->string ?? '';
    }

    public function getLength(): int
    {
        return strlen($this->getString());
    }
}
