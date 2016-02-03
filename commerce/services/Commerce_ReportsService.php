<?php
namespace Craft;

/**
 * Reports service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_ReportsService extends BaseApplicationComponent
{
    public function getRevenueReport($criteria, $startDate, $endDate)
    {
        $results = craft()->db->createCommand()
            ->select('DATE_FORMAT(dateOrdered, "%d-%b-%y") as date, sum(totalPrice) as revenue')
            ->from('commerce_orders')
            ->group('YEAR(dateOrdered), MONTH(dateOrdered), DAY(dateOrdered)')
            ->queryAll();

        $currency = craft()->commerce_settings->getOption('defaultCurrency');

        $reportDataTable = $this->getReportDataTable($startDate, $endDate, $results);
        $scale = $this->getScale($startDate, $endDate);
        $currencyFormat = $this->getCurrencyFormat($currency);
        $total = 0;
        $totalHtml = craft()->numberFormatter->formatCurrency($total, strtoupper($currency));

        $response = array(
            'reportDataTable' => $reportDataTable,
            'scale' => $scale,
            'currencyFormat' => $currencyFormat,
            'total' => $total,
            'totalHtml' => $totalHtml,
        );

        return $response;
    }

    public function getReportDataTable($startDate, $endDate, $results)
    {
        $scale = $this->getScale($startDate, $endDate);

        // columns

        $columns = [];

        $columns[] = [
            'type' => 'date',
            'label' => 'Date',
        ];

        $columns[] = [
            'type' => 'currency',
            'label' => 'Revenue',
        ];


        // rows

        $rows = [];

        $cursorCurrent = new DateTime($startDate);

        while($cursorCurrent->getTimestamp() < $endDate->getTimestamp())
        {
            $cursorStart = new DateTime($cursorCurrent);
            $cursorCurrent->modify('+1 '.$scale);
            $cursorEnd = $cursorCurrent;

            $row = [
                ['value' => strftime("%e-%b-%y", $cursorStart->getTimestamp())], // date
                ['value' => 0] // revenue
            ];

            foreach($results as $result)
            {
                if($result['date'] == strftime("%e-%b-%y", $cursorStart->getTimestamp()))
                {
                    $row = [
                        ['value' => $result['date']], // date
                        ['value' => $result['revenue']] // revenue
                    ];
                }
            }

            $rows[] = $row;
        }

        return [
            'columns' => $columns,
            'rows' => $rows
        ];
    }

    public function getScale($startDate, $endDate)
    {
        // auto scale

        $numberOfDays = floor(($endDate->getTimestamp() - $startDate->getTimestamp()) / (60*60*24));

        if ($numberOfDays > 360)
        {
            $scale = 'year';
        }
        elseif($numberOfDays > 60)
        {
            $scale = 'month';
        }
        else
        {
            $scale = 'day';
        }

        return $scale;
    }

    private function getCurrencyFormat($currency)
    {
        $currencySymbol = craft()->locale->getCurrencySymbol($currency);
        $currencyFormat = craft()->locale->getCurrencyFormat();

        if(strpos($currencyFormat, ";") > 0)
        {
            $currencyFormatArray = explode(";", $currencyFormat);
            $currencyFormat = $currencyFormatArray[0];
        }

        $pattern = '/[#0,.]/';
        $replacement = '';
        $currencyFormat = preg_replace($pattern, $replacement, $currencyFormat);

        if(strpos($currency, "¤") === 0)
        {
            // symbol at beginning
            $currencyD3Format = [str_replace('¤', $currencySymbol, $currencyFormat), ''];
        }
        else
        {
            // symbol at the end
            $currencyD3Format = ['', str_replace('¤', $currencySymbol, $currencyFormat)];
        }

        return $currencyD3Format;
    }
}
