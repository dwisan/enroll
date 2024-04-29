<?php
/**
 * @filesource modules/index/models/categories.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Index\Categories;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=categories
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่านหมวดหมู่สำหรับใส่ลงใน DataTable
     * ถ้าไม่มีคืนค่าข้อมูลเปล่าๆ 1 แถว
     *
     * @param string $type
     * @param bool $multiple_language false (default) ภาษาเดียว, true หลายภาษา
     *
     * @return array
     */
    public static function toDataTable($type, $multiple_language = false)
    {
        $datas = ['category_id' => 1];
        foreach (Language::installedLanguage() as $lng) {
            $datas[$lng] = '';
        }
        // Query ข้อมูลหมวดหมู่จากตาราง category
        $query = static::createQuery()
            ->select('category_id', 'language', 'topic')
            ->from('category')
            ->where(array('type', $type))
            ->order('category_id');
        $result = [];
        foreach ($query->execute() as $item) {
            if ($multiple_language) {
                if (isset($result[$item->category_id][$item->language])) {
                    $result[$item->category_id][$item->language] = $item->topic;
                } elseif (!isset($result[$item->category_id])) {
                    $datas['category_id'] = $item->category_id;
                    if (isset($datas[$item->language])) {
                        $datas[$item->language] = $item->topic;
                    }
                    $result[$item->category_id] = $datas;
                }
            } else {
                $result[$item->category_id] = array(
                    'category_id' => $item->category_id,
                    'topic' => $item->topic
                );
            }
        }
        if (empty($result)) {
            $result[1]['category_id'] = 1;
            if ($multiple_language) {
                foreach (Language::installedLanguage() as $lng => $label) {
                    $result[1][$lng] = '';
                }
            } else {
                $result[1]['topic'] = '';
            }
        }
        return $result;
    }

    /**
     * บันทึกหมวดหมู่ (categories.php)
     *
     * @param Request $request
     */
    public function submit(Request $request)
    {
        $ret = [];
        // session, token, can_config, ไม่ใช่สมาชิกตัวอย่าง
        if ($request->initSession() && $request->isSafe() && $login = Login::isMember()) {
            if (Login::notDemoMode($login) && Login::checkPermission($login, 'can_config')) {
                try {
                    // ค่าที่ส่งมา
                    $type = $request->post('type')->topic();
                    $save = [];
                    $category_exists = [];
                    foreach ($request->post('category_id', [])->topic() as $key => $value) {
                        if (isset($category_exists[$value])) {
                            $ret['ret_category_id_'.$key] = Language::replace('This :name already exist', array(':name' => 'ID'));
                        } elseif ($value != '') {
                            $category_exists[$value] = $value;
                            $save[$key]['category_id'] = $value;
                        }
                    }
                    if ($request->post('topic')->exists()) {
                        foreach ($request->post('topic')->topic() as $key => $value) {
                            if (isset($save[$key]) && $value != '') {
                                $save[$key]['topic'][''] = $value;
                            }
                        }
                    }
                    foreach (Language::installedLanguage() as $lng => $label) {
                        if ($request->post($lng)->exists()) {
                            foreach ($request->post($lng, [])->topic() as $key => $value) {
                                if (isset($save[$key]) && $value != '') {
                                    $save[$key]['topic'][$lng] = $value;
                                }
                            }
                        }
                    }
                    if (empty($ret)) {
                        // ชื่อตาราง
                        $table_name = $this->getTableName('category');
                        // db
                        $db = $this->db();
                        // ลบข้อมูลเดิม
                        $db->delete($table_name, array('type', $type), 0);
                        // เพิ่มข้อมูลใหม่
                        foreach ($save as $item) {
                            foreach ($item['topic'] as $lng => $topic) {
                                $db->insert($table_name, array(
                                    'category_id' => $item['category_id'],
                                    'type' => $type,
                                    'language' => $lng,
                                    'topic' => $topic
                                ));
                            }
                        }
                        // log
                        \Index\Log\Model::add(0, 'index', 'Save', Language::get('CATEGORIES', $type, $type), $login['id']);
                        // คืนค่า
                        $ret['alert'] = Language::get('Saved successfully');
                        $ret['location'] = 'reload';
                        // เคลียร์
                        $request->removeToken();
                    }
                } catch (\Kotchasan\InputItemException $e) {
                    $ret['alert'] = $e->getMessage();
                }
            }
            if (empty($ret)) {
                $ret['alert'] = Language::get('Unable to complete the transaction');
            }
            // คืนค่า JSON
            echo json_encode($ret);
        }
    }
}
