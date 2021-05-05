<?php
/**
 * Copyright © 2015 Excellence . All rights reserved.
 */
namespace Excellence\Gst\Model\Order\Pdf\Items\Creditmemo;

/**
 * Sales Order Creditmemo Pdf default items renderer
 */
class DefaultCreditmemo extends \Magento\Sales\Model\Order\Pdf\Items\Creditmemo\DefaultCreditmemo
{
    /**
     * Draw process
     *
     * @return void
     */
    public function draw()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $scopeConfig = $objectManager->create('Magento\Framework\App\Config\ScopeConfigInterface');
        $status = $scopeConfig->getValue(
            "gst/excellence/status",
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
        if(!$status){
            parent::draw();
        }
        else{
            $productionState = $scopeConfig->getValue('gst/excellence/production_state', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            $productionState = str_replace(' ', '', strtolower($productionState));
            $order = $this->getOrder();
            $orderDate = $order->getCreatedAt();
            $orderDate = date_create($orderDate);
            $date = "2017-06-30 11:59:59";
            $gstDate=date_create($date);
            $gstOrderStatus = false;
            if($gstDate < $orderDate){
                $gstOrderStatus = true;
            }
            $state = $order->getShippingAddress()->getRegion();
            $state = str_replace(' ', '', strtolower($state));
            $item = $this->getItem();
            $productFactory = $objectManager->create('Magento\Catalog\Model\Product');
            $productData = $productFactory->load($item->getProductId());
            $productHsn = $productData->getHsn();

            $pdf = $this->getPdf();
            $page = $this->getPage();
            $lines = [];

            // draw Product name
            $lines[0] = [['text' => $this->string->split($item->getName(), 35, true, true), 'feed' => 35]];

            // draw SKU
            $lines[0][] = [
                'text' => $this->string->split($this->getSku($item), 17),
                'feed' => 255,
                'align' => 'right',
            ];

//        // draw HSN
//        $lines[0][] = [
//            'text' => $this->string->split($productHsn, 17),
//            'feed' => 315,
//            'align' => 'right',
//        ];

            // draw Total (ex)
            $lines[0][] = [
                'text' => $order->formatPriceTxt($item->getRowTotal()),
                'feed' => 330,
                'font' => 'bold',
                'align' => 'right',
            ];

            // draw Discount
            $lines[0][] = [
                'text' => $order->formatPriceTxt(-$item->getDiscountAmount()),
                'feed' => 375,
                'font' => 'bold',
                'align' => 'right',
            ];

            // draw QTY
            $lines[0][] = ['text' => $item->getQty() * 1, 'feed' => 505, 'font' => 'bold', 'align' => 'right'];

            if(($state==$productionState) && $gstOrderStatus){
                $sgst_percent = $item->getOrderItem()->getTaxPercent()/2;
                $stateTax = $item->getTaxAmount() / 2;
                $data = $order->formatPriceTxt($stateTax);
                $per =  "(" . number_format($sgst_percent, 2, ".", " ") . "%" . ")";
                $total_sgst = $this->splitString($data, $per);
                // draw SGST
                $lines[0][] = [
                    'text' => $total_sgst,
                    'feed' => 420,
                    'font' => 'bold',
                    'align' => 'right',
                ];

                // draw CGST
                $lines[0][] = [
                    'text' => $total_sgst,
                    'feed' => 470,
                    'font' => 'bold',
                    'align' => 'right',
                ];
            }
            else{
                $igst_amount = $item->getTaxAmount();
                $igst_percent = $item->getOrderItem()->getTaxPercent();
                $data = $order->formatPriceTxt($igst_amount);
                $per =  "(" . number_format($igst_percent, 2, ".", " ") . "%" . ")";
                $total_igst = $this->splitString($data, $per);
                // draw Tax/IGST
                $lines[0][] = [
                    'text' => $total_igst,
                    'feed' => 450,
                    'font' => 'bold',
                    'align' => 'right',
                ];
            }

            // draw Total (inc)
            $subtotal = $item->getRowTotal() +
                $item->getTaxAmount() +
                $item->getDiscountTaxCompensationAmount() -
                $item->getDiscountAmount();
            $lines[0][] = [
                'text' => $order->formatPriceTxt($subtotal),
                'feed' => 565,
                'font' => 'bold',
                'align' => 'right',
            ];

            // draw options
            $options = $this->getItemOptions();
            if ($options) {
                foreach ($options as $option) {
                    // draw options label
                    $lines[][] = [
                        'text' => $this->string->split($this->filterManager->stripTags($option['label']), 40, true, true),
                        'font' => 'italic',
                        'feed' => 35,
                    ];

                    // draw options value
                    $printValue = isset(
                        $option['print_value']
                    ) ? $option['print_value'] : $this->filterManager->stripTags(
                        $option['value']
                    );
                    $lines[][] = ['text' => $this->string->split($printValue, 30, true, true), 'feed' => 40];
                }
            }

            $lineBlock = ['lines' => $lines, 'height' => 20];

            $page = $pdf->drawLineBlocks($page, [$lineBlock], ['table_header' => true]);
            $this->setPage($page);
        }

    }

    /**
     * @param $data
     * @param $per
     * @return array
     */
    public function splitString($data, $per)
    {
        if (strlen($data) > strlen($per)) {
            $total_cgst = str_split($data . $per, strlen($data));
            return $total_cgst;
        } else {
            for ($i = strlen($data); $i < strlen($per); $i++) {
                $data = $data . " ";
            }
            $total_cgst = str_split($data . $per, strlen($per));
            return $total_cgst;
        }
    }
}