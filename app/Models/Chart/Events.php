<?php

/**
 * Tirreno ~ Open source user analytics
 * Copyright (c) Tirreno Technologies Sàrl (https://www.tirreno.com)
 *
 * Licensed under GNU Affero General Public License version 3 of the or any later version.
 * For full copyright and license information, please see the LICENSE
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Tirreno Technologies Sàrl (https://www.tirreno.com)
 * @license       https://opensource.org/licenses/AGPL-3.0 AGPL License
 * @link          https://www.tirreno.com Tirreno(tm)
 */

namespace Models\Chart;

class Events extends Base {
    protected $DB_TABLE_NAME = 'event';

    public function getData(int $apiKey): array {
        $data = $this->getFirstLine($apiKey);

        $ox = array_column($data, 'ts');
        $l1 = array_column($data, 'event_count');
        $l2 = array_column($data, 'users_count');
        $l3 = array_column($data, 'event_alert_type_count');

        return $this->addEmptyDays([$ox, $l1, $l2, $l3]);
    }

    private function getFirstLine(int $apiKey): array {
        $request = $this->f3->get('REQUEST');
        $dateRange = $this->getDatesRange($request);
        if (!$dateRange) {
            $dateRange = [
                'endDate' => date('Y-m-d H:i:s'),
                'startDate' => date('Y-m-d H:i:s', 0),
            ];
        }
        $offset = \Utils\TimeZones::getCurrentOperatorOffset();
        [$typesParams, $flatIds] = $this->getArrayPlaceholders(\Utils\Constants::ALERT_EVENT_TYPES);
        $params = [
            ':api_key'      => $apiKey,
            ':end_time'     => $dateRange['endDate'],
            ':start_time'   => $dateRange['startDate'],
            ':resolution'   => $this->getResolution($request),
            ':offset'       => strval($offset),
        ];
        $params = array_merge($params, $typesParams);

        $query = (
            "SELECT
                EXTRACT(EPOCH FROM date_trunc(:resolution, event.time + :offset))::bigint AS ts,
                COUNT(event.id) AS event_count,
                COUNT(DISTINCT event.account) AS users_count,
                COUNT(CASE WHEN event_type.value IN ({$flatIds}) THEN TRUE END) AS event_alert_type_count

            FROM
                event

            LEFT JOIN event_type
            ON event.type = event_type.id

            WHERE
                event.key = :api_key AND
                event.time >= :start_time AND
                event.time <= :end_time

            GROUP BY ts
            ORDER BY ts"
        );

        return $this->execQuery($query, $params);
    }
}
