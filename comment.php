<?php
/**
 * Enhanced import tool: module for PrestaShop 1.3
 *
 * @author    Maksim T. <zapalm@yandex.com>
 * @copyright 2010 Maksim T.
 * @link      https://prestashop.modulez.ru/en/import-and-export-data/14-enhanced-import-tool.html The module's homepage
 * @license   https://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

/**
 * Comment model.
 *
 * @author Maksim T. <zapalm@yandex.com>
 */
class Comment extends ObjectModel
{
    public $id_product;
    public $id_customer;
    public $content;
    public $grade;
    public $validate;
    public $date_add;

    protected $fieldsRequired = array('id_product', 'id_customer', 'content');
    protected $fieldsSize = array('content' => 1750);

    protected $table = 'product_comment';
    protected $identifier = 'id_product_comment';

    /**
     * @inheritDoc
     *
     * @author Maksim T. <zapalm@yandex.com>
     */
    public function getFields()
    {
        parent::validateFields();

        if (isset($this->id)) {
            $fields['id_product_comment'] = intval($this->id);
        }

        $fields['id_product']  = pSQL($this->id_product);
        $fields['id_customer'] = pSQL($this->id_customer);
        $fields['content']     = pSQL($this->content);

        return $fields;
    }

    /**
     * Checks is a comment exists.
     *
     * @param int $id
     *
     * @return bool
     *
     * @author Maksim T. <zapalm@yandex.com>
     */
    public static function isCommentExists($id)
    {
        $row = Db::getInstance()->getRow('
            SELECT `id_product_comment`
            FROM ' . _DB_PREFIX_ . 'product_comment p
            WHERE p.`id_product_comment` = ' . intval($id)
        );

        return isset($row['id_product_comment']);
    }
}