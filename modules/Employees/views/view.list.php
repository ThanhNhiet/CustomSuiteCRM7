<?php
if(!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('include/MVC/View/views/view.list.php');

class EmployeesViewList extends ViewList {
    public function display() {
        parent::display();
        echo '
        <div id="rc_modal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.4);">
            <div style="background-color:#fefefe; margin: 2% auto; padding:0; border:1px solid #888; width:95%; height: 90vh; border-radius: 5px; display: flex; flex-direction: column; box-shadow: 0 4px 15px 0 rgba(0,0,0,0.3);">
                
                <div style="padding: 10px; background: #f0f0f0; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; gap: 5px;">
                        <button type="button" onclick="rcNav(\'home\')" style="cursor:pointer; padding: 5px 10px; border: 1px solid #999; background: #e6f7ff; border-radius: 4px; margin-left: 10px;">Directory</button>
                    </div>
                    <span style="font-weight:bold; color: #555;">Chat</span>
                    <span onclick="document.getElementById(\'rc_modal\').style.display=\'none\'" style="color:#666; font-size:24px; font-weight:bold; cursor:pointer; padding: 0 10px;">&times;</span>
                </div>

                <div style="flex-grow: 1;">
                    <iframe id="rc_iframe" src="" style="width:100%; height:100%; border:none;"></iframe>
                </div>
            </div>
        </div>

        <script type="text/javascript">
            var rocketDirectoryUrl = "http://localhost:3000/directory/channels?layout=embedded";

            // Hàm mở Modal
            function openRocketChatModal(url) {
                if(!url) url = rocketDirectoryUrl;
                var iframe = document.getElementById("rc_iframe");
                
                // Kiểm tra xem iframe có tồn tại không trước khi set src
                if(iframe) {
                    iframe.src = url; 
                    document.getElementById("rc_modal").style.display = "block";
                } else {
                    console.error("Không tìm thấy thẻ iframe có id rc_iframe");
                }
            }

            // Hàm điều hướng
            function rcNav(action) {
                var iframe = document.getElementById("rc_iframe");
                if(iframe) {
                    if (action === "home") iframe.src = rocketDirectoryUrl;
                }
            }
        </script>
        ';
    }
}
?>