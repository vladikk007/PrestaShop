<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Tests\Integration\Behaviour\Features\Context\Domain\Product;

use Behat\Gherkin\Node\TableNode;
use PrestaShop\PrestaShop\Core\Domain\Product\Command\AddProductCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductException;
use Product;

class AddProductFeatureContext extends AbstractProductFeatureContext
{
    /**
     * @When I add product :productReference with following information:
     *
     * @param string $productReference
     * @param TableNode $table
     */
    public function addProduct(string $productReference, TableNode $table): void
    {
        $data = $this->localizeByRows($table);

        try {
            $productId = $this->getCommandBus()->handle(new AddProductCommand(
                $data['type'],
                $data['name'] ?? []
            ));

            $this->getSharedStorage()->set($productReference, $productId->getValue());
        } catch (ProductException $e) {
            $this->setLastException($e);
        }
        // Fix issue related to modules hooked on `actionProductSave` and calling `Product::priceCalculation()`
        // leading to cache issues later
        Product::resetStaticCache();
    }
}
