<?php
define('SECRET_KEY', 'hello~!');


class ControllerExtensionModuleImport extends Controller
{
    public function index()
    {
        $request = file_get_contents('php://input');
        $data    = json_decode($request, true);

        if ($data['secret_key'] !== SECRET_KEY) {
            echo 'No :(';

            return;
        }

        $image          = null;
        $manufacturerId = null;

        if (!empty($data['image'])) {
            $image = "image = '".$this->db->escape($data['image'])."',";
        }

        $dbManufacturers = $this->db->query(
            'SELECT * FROM '.DB_PREFIX.'manufacturer'
        )->rows;

        if (!empty($data['manufacturer'])) {
            $issetManufacturer = false;

            foreach ($dbManufacturers as $dbManufacturer) {
                if ($dbManufacturer['name'] === $data['manufacturer']) {
                    $manufacturerId    = (int) $dbManufacturer['manufacturer_id'];
                    $issetManufacturer = true;
                    continue;
                }
            }

            // adding new manufacturer if not exists
            if (!$issetManufacturer) {
                $this->db->query(
                    'INSERT INTO '.DB_PREFIX.'manufacturer SET name = "'.$this->db->escape($data['manufacturer']).'"'
                );

                $manufacturerId = (int) $this->db->getLastId();
            }
        }

        $this->db->query(
            'INSERT INTO '.DB_PREFIX."product SET
                model = '".$this->db->escape($data['model'])."',
                quantity = 999,
                minimum = 1, 
                subtract = 0,
                manufacturer_id = ".$manufacturerId.",
                stock_status_id = 5, 
                date_available = NOW(),
                shipping = 1, 
                price = '".(float) $data['price']."', 
                points = 0, 
                weight = '".(float) $data['weight']."',
                weight_class_id = '".(int) $data['weight_class_id']."', 
                length = '".(float) $data['length']."',
                width = '".(float) $data['width']."', 
                height = '".(float) $data['height']."',
                length_class_id = '".(int) $data['length_class_id']."',
                status = 1,
                ".$image.'
                date_added = NOW(),
                date_modified = NOW()'
        );

        $productId = (int) $this->db->getLastId();

        echo 'Product ID '.$productId;

        $this->db->query(
            'INSERT INTO '.DB_PREFIX."product_description SET
             product_id = '".$productId."',
             language_id = '2',
             name = '".$this->db->escape($data['name'])."', 
             description_short = '".$this->db->escape($data['description_short'])."',
             description2 = '".$this->db->escape($data['description_short'])."',
             meta_title = '".$this->db->escape($data['name'])."', 
             meta_description = '', 
             meta_keyword = ''"
        );

        $this->db->query(
            'INSERT INTO '.DB_PREFIX."product_to_store
         SET product_id = '".$productId."', store_id = 0"
        );

        $this->db->query(
            'INSERT INTO '.DB_PREFIX."product_to_layout
         SET product_id = '".$productId."', store_id = 0, layout_id = 2"
        );

        $dbAttributes = $this->db->query(
            'SELECT * FROM '.DB_PREFIX.'attribute_description ad
                LEFT JOIN '.DB_PREFIX.'attribute a ON a.attribute_id = ad.attribute_id
                WHERE language_id = 2'
        )->rows;

        $dbAttributeGroups = $this->db->query(
            'SELECT attribute_group_id, name 
                FROM '.DB_PREFIX.'attribute_group_description WHERE language_id = 2'
        )->rows;

        if (!empty($data['attributes'])) {
            foreach ($data['attributes'] as $attribute) {
                $issetGroup     = false;
                $issetAttribute = false;
                $group          = $attribute['group'];

                // search groups
                foreach ($dbAttributeGroups as $dbAttributeGroup) {
                    if ($dbAttributeGroup['name'] === $group) {
                        $issetGroup            = true;
                        $attribute['group_id'] = $dbAttributeGroup['attribute_group_id'];
                        continue;
                    }
                }

                // adding new groups
                if (!$issetGroup) {
                    $this->db->query('INSERT INTO '.DB_PREFIX.'attribute_group SET sort_order = 0');
                    $groupId = (int) $this->db->getLastId();

                    $attribute['group_id'] = $groupId;

                    $this->db->query(
                        'INSERT INTO '.DB_PREFIX.'attribute_group_description
                             SET attribute_group_id = '.$groupId.', language_id = 2, 
                             name = "'.$this->db->escape($group).'"'
                    );

                    $dbAttributeGroups[] = [
                        'name'               => $group,
                        'attribute_group_id' => $groupId,
                    ];
                }

                // search values
                foreach ($dbAttributes as $dbAttribute) {
                    if ($dbAttribute['name'] === $attribute['name']) {
                        $issetAttribute            = true;
                        $attribute['attribute_id'] = $dbAttribute['attribute_id'];
                        continue;
                    }
                }

                if (!$issetAttribute) {
                    $this->db->query(
                        'INSERT INTO '.DB_PREFIX.'attribute SET attribute_group_id = '.$attribute['group_id']
                    );
                    $attributeId = $this->db->getLastId();

                    $attribute['attribute_id'] = $attributeId;

                    $this->db->query(
                        'INSERT INTO '.DB_PREFIX.'attribute_description 
                    SET language_id = 2, attribute_id = '.(int) $attributeId.',
                     name="'.$this->db->escape($attribute['name']).'"'
                    );

                    $dbAttributes[] = $attribute;
                }

                $this->db->query(
                    'INSERT INTO '.DB_PREFIX."product_attribute SET product_id = '".$productId."', attribute_id = '"
                    .(int) $attribute['attribute_id']."', language_id = 2, text = '".$this->db->escape($attribute['value'])."'"
                );
            }
        }

        if (!empty($data['product_image'])) {
            foreach ($data['product_image'] as $product_image) {
                $this->db->query(
                    'INSERT INTO '.DB_PREFIX."product_image SET product_id = '".$productId."',
                     image = '".$this->db->escape($product_image)."', sort_order = 0"
                );
            }
        }

        $dbCategories = $this->db->query(
            'SELECT category_id, name 
                FROM '.DB_PREFIX.'category_description WHERE language_id = 2'
        )->rows;

        if (!empty($data['category'])) {
            $issetCategory = false;
            $categoryId    = null;

            foreach ($dbCategories as $dbCategory) {
                if ($dbCategory['name'] === $data['category']) {
                    $categoryId    = (int) $dbCategory['category_id'];
                    $issetCategory = true;
                    continue;
                }
            }

            // adding new category if not exists
            if (!$issetCategory) {
                $this->db->query(
                    'INSERT INTO '.DB_PREFIX.'category 
                    SET `top` = 1, `column` = 1, status = 1, date_added = NOW(), date_modified = NOW()'
                );

                $categoryId = (int) $this->db->getLastId();

                $this->db->query(
                    'INSERT INTO '.DB_PREFIX.'category_description
                    SET category_id = '.$categoryId.', name = "'.$this->db->escape($data['category']).'", 
                    meta_title = "'.$this->db->escape($data['category']).'", language_id = 2'
                );

                $this->db->query(
                    'INSERT INTO '.DB_PREFIX.'category_to_store SET category_id = '.$categoryId.', store_id = 0'
                );

                $this->db->query(
                    'INSERT INTO '.DB_PREFIX.'category_path SET category_id = '.$categoryId.', path_id = '.$categoryId
                );

                $this->db->query(
                    'INSERT INTO '.DB_PREFIX.'category_to_layout SET category_id = '.$categoryId.', store_id = 0, layout_id = 3'
                );
            }

            if (!empty($categoryId)) {
                $this->db->query(
                    'INSERT INTO '.DB_PREFIX."product_to_category SET product_id = '".$productId."', category_id = '".$categoryId."'"
                );
            }
        }

        if (!empty($data['url'])) {
            $this->db->query(
                'INSERT INTO '.DB_PREFIX."seo_url SET store_id = 0, language_id = 2, 
                query = 'product_id=".$productId."', keyword = '".$this->db->escape($data['url'])."'"
            );
        }


        $this->cache->delete('product');
        $this->cache->delete('category');
    }
}
