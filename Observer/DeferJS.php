<?php
/**
 * GiaPhuGroup Co., Ltd.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the GiaPhuGroup.com license that is
 * available through the world-wide-web at this URL:
 * https://www.giaphugroup.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    PHPCuong
 * @package     PHPCuong_DeferParsingOfJavascript
 * @copyright   Copyright (c) 2019-2020 GiaPhuGroup Co., Ltd. All rights reserved. (http://www.giaphugroup.com/)
 * @license     https://www.giaphugroup.com/LICENSE.txt
 */

namespace PHPCuong\DeferParsingOfJavascript\Observer;

class DeferJS implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return boolean|void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $response = $observer->getEvent()->getResponse();
        $htmlContent = $response->getBody();

        if (stripos($htmlContent, '<!DOCTYPE html') !== false) {
            $headers = $response->getHeaders()->toArray();
            if (array_key_exists('Content-Type', $headers)
                && $headers['Content-Type'] == 'application/json'
            ) {
                return false;
            }

            // Set the body with the new HTML content
            $response->setBody(
                $this->moveJavascriptToFooter($htmlContent)
            );
        }
    }

    /**
     * Method calls moving the Javascript to the footer before </body>.
     *
     * @param string $htmlContent
     * @return string
     */
    private function moveJavascriptToFooter($htmlContent)
    {
        // Move all the JS to footer
        $conditionalJsPattern = '@(?:<script type="text/javascript"|<script)(.*)</script>@msU';
        preg_match_all($conditionalJsPattern, $htmlContent, $_matches);
        $_js_if = implode('', $_matches[0]);
        $htmlContent = preg_replace($conditionalJsPattern, '', $htmlContent);
        $htmlContent = str_replace('</body>', $_js_if.'</body>', $htmlContent);
        return $htmlContent;
    }
}
