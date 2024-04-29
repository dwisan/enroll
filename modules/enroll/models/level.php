<?php
/**
 * @filesource modules/enroll/models/level.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Enroll\Level;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=enroll-level
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่าน Level สำหรับใส่ลงใน DataTable
     * ถ้าไม่มีคืนค่าข้อมูลเปล่าๆ 1 แถว.
     *
     * @return array
     */
    public static function toDataTable()
    {
        $query = static::createQuery()
            ->select('category_id', 'topic')
            ->from('category')
            ->where(array(
                array('type', 'enroll'),
                array('sub_category', 0)
            ))
            ->order('category_id');
        $result = [];
        foreach ($query->execute() as $item) {
            $result[] = array(
                'category_id' => $item->category_id,
                'topic' => $item->topic
            );
        }
        if (empty($result)) {
            $result[] = array(
                'category_id' => 1,
                'topic' => ''
            );
        }

        return $result;
    }

    /**
     * ลิสต์รายการ Level
     * สำหรับใส่ลงใน select
     *
     * @return array
     */
    public static function toSelect()
    {
        $query = static::createQuery()
            ->select('category_id', 'topic')
            ->from('category')
            ->where(array(
                array('type', 'enroll'),
                array('sub_category', 0)
            ))
            ->order('category_id')
            ->cacheOn();
        $result = [];
        foreach ($query->execute() as $item) {
            $result[$item->category_id] = $item->topic;
        }

        return $result;
    }

    /**
     * บันทึก Level
     *
     * @param Request $request
     */
    public function submit(Request $request)
    {
        $ret = [];
        // session, token, สามารถจัดการการลงทะเบียนได้, ไม่ใช่สมาชิกตัวอย่าง
        if ($request->initSession() && $request->isSafe() && $login = Login::isMember()) {
            if (Login::notDemoMode($login) && Login::checkPermission($login, 'can_manage_enroll')) {
                try {
                    // ค่าที่ส่งมา
                    $save = [];
                    $category_exists = [];
                    foreach ($request->post('category_id', [])->toInt() as $key => $value) {
                        if (isset($category_exists[$value])) {
                            $ret['ret_category_id_'.$key] = Language::replace('This :name already exist', array(':name' => 'ID'));
                        } else {
                            $category_exists[$value] = $value;
                            $save[$key]['category_id'] = $value;
                            $save[$key]['sub_category'] = 0;
                            $save[$key]['type'] = 'enroll';
                        }
                    }
                    foreach ($request->post('topic', [])->topic() as $key => $value) {
                        if (isset($save[$key])) {
                            $save[$key]['topic'] = $value;
                        }
                    }
                    if (empty($ret)) {
                        // ชื่อตาราง
                        $table_name = $this->getTableName('category');
                        // db
                        $db = $this->db();
                        // ลบข้อมูลเดิม
                        $db->delete($table_name, array(array('type', 'enroll'), array('sub_category', 0)), 0);
                        // เพิ่มข้อมูลใหม่
                        foreach ($save as $item) {
                            if (isset($item['topic'])) {
                                $db->insert($table_name, $item);
                            }
                        }
                        // Log
                        \Index\Log\Model::add(0, 'enroll', 'Save', Language::get('Education level'), $login['id']);
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
        }
        if (empty($ret)) {
            $ret['alert'] = Language::get('Unable to complete the transaction');
        }
        // คืนค่าเป็น JSON
        echo json_encode($ret);
    }
}
