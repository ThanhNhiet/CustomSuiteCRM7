<?php
$listViewDefs ['Employees'] = 
array (
  'OPEN_CHAT_BUTTON' => array(
    'width' => '10%',
    'label' => 'LBL_CHAT_BUTTON',
    'default' => true,
    'sortable' => false,
    'related_fields' => array(
        'user_name',
    ),
    'customCode' => '
        <button type="button" class="button"
            style="background: #86a0ffff; color: white; border: none; padding: 2px 5px 6px 5px; cursor: pointer; font-size: 10px; border-radius: 4px;"
            onclick=\'openRocketChatModal("http://localhost:3000/direct/{$USER_NAME}?layout=embedded"); event.stopPropagation(); return false;\'>
            <img src="custom/public/img/chatprivate_20x20.png" alt="Chat">
        </button>
    ',
  ),
  'NAME' => 
  array (
    'width' => '20%',
    'label' => 'LBL_LIST_NAME',
    'link' => true,
    'related_fields' => 
    array (
      0 => 'last_name',
      1 => 'first_name',
    ),
    'orderBy' => 'last_name',
    'default' => true,
  ),
  'DEPARTMENT' => 
  array (
    'width' => '10%',
    'label' => 'LBL_DEPARTMENT',
    'link' => true,
    'default' => true,
  ),
  'TITLE' => 
  array (
    'width' => '15%',
    'label' => 'LBL_TITLE',
    'link' => true,
    'default' => true,
  ),
  'REPORTS_TO_NAME' => 
  array (
    'width' => '15%',
    'label' => 'LBL_LIST_REPORTS_TO_NAME',
    'link' => true,
    'sortable' => false,
    'default' => true,
  ),
  'EMAIL1' => 
  array (
    'width' => '15%',
    'label' => 'LBL_LIST_EMAIL',
    'link' => true,
    'customCode' => '{$EMAIL1_LINK}',
    'default' => true,
    'sortable' => false,
  ),
  'PHONE_WORK' => 
  array (
    'width' => '10%',
    'label' => 'LBL_LIST_PHONE',
    'link' => true,
    'default' => true,
  ),
  'EMPLOYEE_STATUS' => 
  array (
    'width' => '10%',
    'label' => 'LBL_LIST_EMPLOYEE_STATUS',
    'link' => false,
    'default' => true,
  ),
  'DATE_ENTERED' => 
  array (
    'width' => '10%',
    'label' => 'LBL_DATE_ENTERED',
    'default' => true,
  ),
);
;
?>
