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

            $htmlContent = $this->minifyHtml($htmlContent);
            $htmlContent = $this->moveJavascriptToFooter($htmlContent);

            // Set the body with the new HTML content
            $response->setBody($htmlContent);
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

    /**
     * Method calls compressing the HTML before rendering.
     *
     * @param string $htmlContent
     * @return string
     */
    private function minifyHtml($htmlContent)
    {
        $uncompressed = strlen($htmlContent);
        $pattern = '/<(?<script>script).*?<\/script\s*>|<(?<style>style).*?<\/style\s*>|<!(?<comment>--).*?-->|<(?<tag>[\/\w.:-]*)(?:".*?"|\'.*?\'|[^\'">]+)*>|(?<text>((<[^!\/\w.:-])?[^<]*)+)|/si';
        preg_match_all($pattern, $htmlContent, $matches, PREG_SET_ORDER);
        $overriding = false;
        $raw_tag = false;
        // Variable reused for output
        $result = '';
        foreach ($matches as $token) {
            $tag = isset($token['tag']) ? strtolower($token['tag']) : null;
            $content = $token[0];
            if (is_null($tag)) {
                if (!empty($token['script']) || !empty($token['style'])) {
                    $strip = true;
                }
            } else {
                if ($tag == 'pre' || $tag == 'textarea') {
                    $raw_tag = $tag;
                } else if ($tag == '/pre' || $tag == '/textarea') {
                    $raw_tag = false;
                } else {
                    if ($raw_tag || $overriding) {
                        $strip = false;
                    } else {
                        $strip = true;
                        // Remove any empty attributes, except:
                        // action, alt, content, src
                        $content = preg_replace('/(\s+)(\w++(?<!\baction|\balt|\bamp|\bcontent|\bsrc)="")/', '$1', $content);
                        // Remove any space before the end of self-closing XHTML tags
                        $content = str_replace(' />', '/>', $content);
                        $content = str_replace('" >', '">', $content);
                    }
                }
            }
            $content = str_replace("\t", ' ', $content);
            $content = str_replace("\n", '', $content);
            $content = str_replace("\r", '', $content);
            while (stristr($content, '  ')) {
                $content = str_replace('  ', ' ', $content);
            }
            $result .= $content;
        }

        $result = str_replace('>  <', '><', $result);
        $result = str_replace('> <', '><', $result);
        // Add the command codes to bottom
        $compressed = strlen($result);
        $savings = ($uncompressed - $compressed) / $uncompressed * 100;
        $savings = round($savings, 2);
        return $result;
    }
}
