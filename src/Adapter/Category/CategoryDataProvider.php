<?php
/**
 * 2007-2017 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
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
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2017 PrestaShop SA
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

namespace PrestaShop\PrestaShop\Adapter\Category;

use ObjectModel;
use PrestaShop\PrestaShop\Adapter\LegacyContext;
use Category;
use Context;

/**
 * This class will provide data from DB / ORM about Category
 */
class CategoryDataProvider
{
    private $languageId;

    public function __construct(LegacyContext $context)
    {
        $this->languageId = $context->getLanguage()->id;
    }

    /**
     * Get a category
     *
     * @param null $idCategory
     * @param null $idLang
     * @param null $idShop
     *
     * @throws \LogicException If the category id is not set
     *
     * @return Category
     */
    public function getCategory($idCategory = null, $idLang = null, $idShop = null)
    {
        if (!$idCategory) {
            throw new \LogicException('You need to provide a category id', 5002);
        }

        $category = new Category($idCategory, $idLang, $idShop);

        if ($category) {
            $category->image = Context::getContext()->link->getCatImageLink($category->name, $category->id);
        }

        return $category;
    }

    /**
     * Get all nested categories
     *
     * @param int|null $root_category
     * @param bool|int $id_lang
     * @param bool $active
     * @param int|null $groups
     * @param bool $use_shop_restriction
     * @param string $sql_filter
     * @param string $sql_sort
     * @param string $sql_limit
     *
     * @return array categories
     */
    public function getNestedCategories($root_category = null, $id_lang = false, $active = true, $groups = null, $use_shop_restriction = true, $sql_filter = '', $sql_sort = '', $sql_limit = '')
    {
        if (!$id_lang) {
            $id_lang = $this->languageId;
        }

        return Category::getNestedCategories($root_category, $id_lang, $active, $groups, $use_shop_restriction, $sql_filter, $sql_sort, $sql_limit);
    }

    /**
     * Return available categories Names - excluding Root category
     *
     * @param int|null $root_category
     * @param bool|int $id_lang
     * @param bool $active return only active categories
     * @param $groups
     * @param bool $use_shop_restriction
     * @param string $sql_filter
     * @param string $sql_sort
     * @param string $sql_limit
     * @return array Categories
     */
    public function getAllCategoriesName($root_category = null, $id_lang = false, $active = true, $groups = null, $use_shop_restriction = true, $sql_filter = '', $sql_sort = '', $sql_limit = '')
    {
        if (!$id_lang) {
            $id_lang = $this->languageId;
        }

        $categories = Category::getAllCategoriesName($root_category, $id_lang, $active, $groups, $use_shop_restriction, $sql_filter, $sql_sort, $sql_limit);
        array_shift($categories);
        return $categories;
    }

    /**
     * Return a simple array id/name of categories for a specified product
     * @param Product $product
     *
     * @return array Categories
     */
    public function getCategoriesByProduct(ObjectModel $product)
    {
        $allCategories = $this->getAllCategoriesName();
        $productCategories = $product->getCategories();

        $results = [];
        foreach ($allCategories as $category) {
            foreach($productCategories as $productCategory) {
                if ($productCategory == $category['id_category']) {
                    $results[] = [
                        'id' => $category['id_category'],
                        'name' => $category['name'],
                        'breadcrumb' => $this->getBreadCrumb($category['id_category'])
                    ];
                }
                $productCategories[$category['name']] = $category['id_category'];
            }

        }

        return $results;
    }

    /**
     * Return a simple array id/name of categories
     *
     * @return array Categories
     */
    public function getCategoriesWithBreadCrumb()
    {
        $context = \ContextCore::getContext();
        $db = \DbCore::getInstance();
        
        return $db->executeS('SELECT cl.`id_category` as "id", cl.`name`, (SELECT GROUP_CONCAT(cl.name SEPARATOR "'.$delimiter.'")
                                FROM `'._DB_PREFIX_.'category` c
                                INNER JOIN `'._DB_PREFIX_.'category_shop` category_shop ON (category_shop.id_category = c.id_category AND category_shop.id_shop = '.$context->shop->id.')
                                INNER JOIN `'._DB_PREFIX_.'category_lang` cl ON (c.`id_category` = cl.`id_category` AND cl.id_shop = ' . $context->shop->id . ' AND cl.id_lang = ' . $context->language->id . ')
                                RIGHT JOIN `'._DB_PREFIX_.'category` c2 ON  c.`nleft` <= c2.`nleft` AND c.`nright` >= c2.`nright`
                                WHERE 1 AND c.id_category IS NOT NULL AND c.`level_depth` > 0 AND c2.`id_category` = X.`id_category`) AS "breadcrumb"
                                FROM `'._DB_PREFIX_.'category` X
                                INNER JOIN `'._DB_PREFIX_.'category_shop` XS ON (XS.`id_category` = X.`id_category` AND XS.id_shop = '.$context->shop->id.')
                                INNER JOIN `'._DB_PREFIX_.'category_lang` cl ON (cl.`id_category` = X.`id_category` AND cl.id_shop = ' . $context->shop->id . ' AND cl.id_lang = ' . $context->language->id . ')
                                GROUP BY X.`id_category`
                                ORDER BY X.`nleft` ');
    }

    /**
     * Returns a simple breacrumb from a categoryId, the delimiter can be choosen
     * @param $categoryId
     * @param string $delimiter
     * @return string
     */
    public function getBreadCrumb($categoryId, $delimiter = " > ")
    {
        $currentCategory = new Category($categoryId);
        $categories = $currentCategory->getParentsCategories();
        $categories = array_reverse($categories, true);
        $breadCrumb = '';

        foreach($categories as $category) {
            $breadCrumb .= ' > '.$category['name'];
        }

        return substr($breadCrumb, strlen($delimiter));
    }

    /**
     * Get Categories formatted like ajax_product_file.php using Category::getNestedCategories
     *
     * @param $query
     * @param $limit
     * @param bool $nameAsBreadCrumb
     * @return array
     */
    public function getAjaxCategories($query, $limit, $nameAsBreadCrumb = false)
    {
        if (empty($query)) {
            $query = '';
        } else {
            $query = "AND cl.name LIKE '%".pSQL($query)."%'";
        }

        if (is_integer($limit)) {
            $limit = 'LIMIT ' . $limit;
        } else {
            $limit = '';
        }

        $searchCategories = Category::getAllCategoriesName(
            $root_category = null,
            $id_lang = Context::getContext()->language->id,
            $active = true,
            $groups = null,
            $use_shop_restriction = true,
            $sql_filter = $query,
            $sql_sort = '',
            $limit
        );

        $results = [];
        foreach ($searchCategories as $category) {
            $breadCrumb = $this->getBreadCrumb($category['id_category']);
            $results[] = [
                'id' => $category['id_category'],
                'name' => ($nameAsBreadCrumb ? $breadCrumb : $category['name']),
                'breadcrumb' => $breadCrumb,
                'image' => Context::getContext()->link->getCatImageLink($category['name'], $category['id_category']),
            ];
        }

        return $results;
    }

    public function getRootCategory($idLang = null, Shop $shop = null)
    {
        return Category::getRootCategory($idLang, $shop);
    }
}
