<?php
/**
 * Payline module for PrestaShop
 *
 * @author    Monext <support@payline.com>
 * @copyright Monext - http://www.payline.com
 */

use Dubture\Monolog\Reader\LogReader;

class AdminPaylineLogsAjaxController extends ModuleAdminController
{

    /**
     * Process ajax query to get logs lines
     * @since 2.3.6
     * @return void
     */
    public function ajaxProcessGetLogsLines()
    {
        $logFileContent = $this->getLogsLines(Tools::getValue('logfile'));
        $this->ajaxDie(json_encode($logFileContent));
    }

    /**
     * Return logs file content as array
     * @since 2.3.6
     * @param $logFilename
     * @return array
     */
    protected function getLogsLines($logFilename)
    {
        $logFileContent = [];
        if ($logFilename && in_array($logFilename, $this->module->getPaylineLogsFilesList())) {
            $logFile = $this->module->getPaylineLogsDirectory() . $logFilename.'.log';
            $reader = new LogReader($logFile, 0);

            foreach ($reader as $log) {
                if (!empty($log) && !empty($log['date'])) {

                    $logFileContent[] = [
                        'date' => $log['date']->format('d-m-Y h:i:s'),
                        'logger' => $log['logger'],
                        'level' => $log['level'],
                        'message' => $log['message'],
                        'context' => $log['context'],
                    ];
                }
            }
        }
        return array_reverse($logFileContent);
    }
}
