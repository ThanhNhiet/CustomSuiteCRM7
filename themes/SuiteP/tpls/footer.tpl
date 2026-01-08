</div>
</div>
{if $AUTHENTICATED}
    <footer>
        <div id="copyright_data" class="footer_left">
            <div id="dialog2" title="{$APP.LBL_SUITE_SUPERCHARGED}" style="display: none">
                <p>{$APP.LBL_SUITE_DESC1}</p>
                <br>
                <p>{$APP.LBL_SUITE_DESC2}</p>
                <br>
                <p>{$APP.LBL_SUITE_DESC3}</p>
                <br>
            </div>
            <div id="dialog" title="&copy; {$APP.LBL_SUITE_POWERED_BY}" style="display: none">
                <p>{$COPYRIGHT}</p>
            </div>
            <div id="copyrightbuttons">
                <a id="admin_options">&copy; {$APP.LBL_SUITE_SUPERCHARGED}</a>
                <a id="powered_by">&copy; {$APP.LBL_SUITE_POWERED_BY}</a>
            </div>
        </div>
        {if $STATISTICS}
        <div class="serverstats">
            <span class="glyphicon glyphicon-globe"></span> {$STATISTICS}
        </div>
        {/if}
    	<div class="footer_right">
    		<a onclick="SUGAR.util.top();" href="javascript:void(0)">{$APP.LBL_SUITE_TOP}<span class="suitepicon suitepicon-action-above"></span> </a>
    	</div>
    </footer>
    {/if}
{literal}
    <script>
        SUGAR_callsInProgress++;
        SUGAR._ajax_hist_loaded = true;
        if (SUGAR.ajaxUI)
            YAHOO.util.Event.onContentReady('ajaxUI-history-field', SUGAR.ajaxUI.firstLoad);

        $(function(){
            // fix for campaign wizard
            if($('#wizard').length) {
                var bodyHeight = $('body').height();
                var contentHeight = $('#pagecontent').height() + $('#wizard').height();
                var fieldsetHeight = $('#pagecontent').height() + $('#wizard fieldset').height();
                var height = bodyHeight < contentHeight ? contentHeight : bodyHeight;
                if(fieldsetHeight > height) {
                    height = fieldsetHeight;
                }
                height += 50;
                $('#content').css({
                    'min-height': height + 'px'
                });
            }
        });
    </script>
{/literal}
</div>

<div class="modal fade modal-generic" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                <h4 class="modal-title" id="title-generic">{$APP.LBL_GENERATE_PASSWORD_BUTTON_TITLE}</h4>
            </div>
            <div class="modal-body">
                <div class="container-fluid">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" type="button" data-dismiss="modal">{$APP.LBL_CANCEL}</button>
                <button id="btn-generic" class="btn btn-danger" type="button">{$APP.LBL_OK}</button>
            </div>
        </div>
    </div>
</div>

<div id="rc_modal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.4);">
    <div style="background-color:#fefefe; margin: 2% auto; padding:0; border:1px solid #888; width:95%; height: 90vh; border-radius: 5px; display: flex; flex-direction: column; box-shadow: 0 4px 15px 0 rgba(0,0,0,0.3);">
        
        <div style="padding: 10px; background: #f0f0f0; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; gap: 5px;">
                <button type="button" onclick="rcNav('home')" style="cursor:pointer; padding: 5px 10px; border: 1px solid #999; background: #e6f7ff; border-radius: 4px; margin-left: 10px;">Directory</button>
            </div>
            <span style="font-weight:bold; color: #555;">Chat</span>
            <span onclick="document.getElementById('rc_modal').style.display='none'" style="color:#666; font-size:24px; font-weight:bold; cursor:pointer; padding: 0 10px;">&times;</span>
        </div>

        <div style="flex-grow: 1;">
            <iframe id="rc_iframe" src="" style="width:100%; height:100%; border:none;"></iframe>
        </div>
    </div>
</div>

{literal}
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
            console.error("Không tìm thấy thẻ iframe có id 'rc_iframe'. Hãy kiểm tra lại file footer.tpl");
        }
    }

    // Hàm điều hướng
    function rcNav(action) {
        var iframe = document.getElementById("rc_iframe");
        if(iframe) {
            var win = iframe.contentWindow;
	    if (action === "home") iframe.src = rocketDirectoryUrl;
        }
    }
</script>
<script type="text/javascript">
    // Hàm gọi API lấy danh sách - Nhận tham số targetId
    async function openChatUserList(targetId) {
        // Mặc định là desktop nếu không truyền gì (đề phòng)
        if (!targetId) targetId = 'rc_user_dropdown_desktop';

        // Đường dẫn file PHP (Bạn xác nhận đường dẫn này đúng)
        const url = '/suitecrm7/custom/public/api/get_rc_users.php'; 
        
        // Tìm đúng cái dropdown mình vừa bấm
        const dropdown = document.getElementById(targetId);
        
        try {
            // 1. Reset trạng thái Loading (quan trọng khi bấm lại lần 2)
            if(dropdown) {
                dropdown.innerHTML = '<li style="padding: 15px; text-align: center; color: #777;"><span class="suitepicon suitepicon-action-loading"></span> Đang tải...</li>';
            } else {
                console.error("Không tìm thấy ID: " + targetId);
                return;
            }

            // 2. Gọi file PHP
            const response = await fetch(url); 
            
            // Check nếu file PHP trả về lỗi 404 (đường dẫn sai)
            if (response.status === 404) {
                throw new Error('404 Not Found (Sai đường dẫn file PHP)');
            }
            if (!response.ok) {
                throw new Error('HTTP Error: ' + response.status);
            }
            
            // Kiểm tra xem có phải JSON không trước khi parse (tránh lỗi cú pháp)
            const text = await response.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error("Response không phải JSON:", text);
                throw new Error('Server trả về dữ liệu lỗi (Xem Console)');
            }

            // 3. Xử lý dữ liệu
            if (data.success && data.users) {
                // Truyền cả ID vào hàm render để nó vẽ đúng chỗ
                renderUserDropdown(data.users, targetId); 
            } else {
                console.error("Data error:", data);
                dropdown.innerHTML = '<li style="padding: 10px; text-align: center; color: red;">Không lấy được danh sách user.</li>';
            }

        } catch (error) {
            console.error("Lỗi fetch:", error);
            if(dropdown) dropdown.innerHTML = '<li style="padding: 10px; text-align: center; color: red; font-size: 12px;">' + error.message + '</li>';
        }
    }

    // Hàm vẽ giao diện - Nhận thêm targetId
    function renderUserDropdown(users, targetId) {
        const dropdown = document.getElementById(targetId);
        if (!dropdown) return;

        let listHtml = '';
        
        listHtml += `
            <li style="padding: 10px 15px; border-bottom: 1px solid #f0f0f0; font-weight: bold; font-size: 13px; background: #f9f9f9; color: #555;">
                User status
            </li>
        `;

        let count = 0;
        users.forEach(user => {
            if (user.username !== 'rocket.cat') { 
                let color = '#cccccc'; 
                if (user.status === 'online') color = '#2de0a5'; 
                else if (user.status === 'busy') color = '#f5455c'; 
                else if (user.status === 'away') color = '#ffd21f'; 

                listHtml += `
                    <li onclick="startDirectChat('${user.username}')" 
                        style="border-bottom: 1px solid #f5f5f5; cursor: pointer;">
                        <a href="javascript:void(0)" style="padding: 10px 15px; display: flex; align-items: center; gap: 12px; text-decoration: none; color: #333;">
                            <div style="width: 10px; height: 10px; border-radius: 50%; background: ${color}; flex-shrink: 0;"></div>
                            <div style="font-weight: 600; font-size: 13px;">
                                ${user.name}
                            </div>
                        </a>
                    </li>
                `;
                count++;
            }
        });

        if(count === 0) {
            listHtml += '<li style="padding: 15px; text-align: center; color: #999;">Không có user nào online.</li>';
        }

        dropdown.innerHTML = listHtml;
    }

    function startDirectChat(username) {
        openRocketChatModal("http://localhost:3000/direct/" + username + "?layout=embedded");
    }
</script>
{/literal}

</body>
</html>