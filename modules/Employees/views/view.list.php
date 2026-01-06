<?php
if(!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('include/MVC/View/views/view.list.php');

class EmployeesViewList extends ViewList {
    public function display() {
        parent::display();
        echo '
        <div id="rc_modal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.4);">
            <div style="background-color:#fefefe; margin: 5% auto; padding:0; border:1px solid #888; width:90%; height: 85vh; border-radius: 5px; display: flex; flex-direction: column; box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2);">
                
                <div style="padding: 10px 15px; background: #f5f5f5; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-weight:bold; font-size:18px; color: #333;">Rocket.Chat Directory</span>
                        
                        <button type="button" onclick="resetRocketChat()" style="padding: 5px 10px; cursor: pointer; border: 1px solid #ccc; background: #fff; border-radius: 3px;" title="Quay lại danh sách kênh">
                            <span >Directory Home</span>
                        </button>
                    </div>

                    <span onclick="closeRocketChatModal()" style="color:#aaa; font-size:28px; font-weight:bold; cursor:pointer; line-height: 20px;">&times;</span>
                </div>

                <div style="flex-grow: 1; position: relative;">
                    <iframe id="rc_iframe" src="" style="width:100%; height:100%; border:none;"></iframe>
                </div>
            </div>
        </div>

        <script>
        var rocketBaseUrl = "";

        function openRocketChatModal(url) {
            rocketBaseUrl = url;
            var iframe = document.getElementById("rc_iframe");
            iframe.src = url; 
            
            document.getElementById("rc_modal").style.display = "block";
        }

        // Hàm Reset quay về trang chủ
        function resetRocketChat() {
            if(rocketBaseUrl) {
                document.getElementById("rc_iframe").src = rocketBaseUrl;
            }
        }
        
        function closeRocketChatModal() {
             document.getElementById("rc_modal").style.display = "none";
             document.getElementById("rc_iframe").src = "about:blank";
        }

        window.onclick = function(event) {
            var modal = document.getElementById("rc_modal");
            if (event.target == modal) {
                closeRocketChatModal();
            }
        }
        </script>
        ';
    }
}
?>