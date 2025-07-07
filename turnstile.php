<?php

/**
 * WHMCS Turnstile Hook - 修复位置问题版本
 * 适用于WHMCS 8.x+ 和 Lagom2 主题
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly!');
}

// 配置常量
define('TURNSTILE_ENABLED', true);
define('TURNSTILE_SITE_KEY', '0x4AAAAAABilIdoU1ETicmXC');
define('TURNSTILE_SECRET_KEY', '0x4AAAAAABilIT8A6uUpEwKJIztlwv_bMxw');
define('TURNSTILE_THEME', 'auto');
define('TURNSTILE_ERROR_MSG', '验证码未通过，请重试');

// Hook 1: 验证POST数据
add_hook('ClientAreaPage', 1, function($vars) {
    if (!TURNSTILE_ENABLED || empty($_POST)) {
        return;
    }
    
    $filename = $vars['filename'];
    $shouldValidate = false;
    
    // 检查需要验证的页面
    if ($filename === 'index' && isset($_GET['rp']) && $_GET['rp'] === '/login' && isset($_POST['username'], $_POST['password'])) {
        $shouldValidate = true;
    } elseif ($filename === 'register' && (isset($_POST['firstname']) || isset($_POST['email']))) {
        $shouldValidate = true;
    } elseif ($filename === 'contact' && (isset($_POST['name']) || isset($_POST['email']) || isset($_POST['subject']))) {
        $shouldValidate = true;
    } elseif ($filename === 'submitticket' && (isset($_POST['subject']) || isset($_POST['message']))) {
        $shouldValidate = true;
    } elseif ($filename === 'cart' && isset($_GET['a']) && $_GET['a'] === 'checkout' && (isset($_POST['accepttos']) || isset($_POST['custtype']))) {
        $shouldValidate = true;
    }
    
    if ($shouldValidate && !isset($_POST['promocode'])) {
        if (empty($_POST['cf-turnstile-response'])) {
            die('<script>alert("' . TURNSTILE_ERROR_MSG . '"); window.history.back();</script>');
        }
        
        // 验证Turnstile
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'secret' => TURNSTILE_SECRET_KEY,
                'response' => $_POST['cf-turnstile-response'],
                'remoteip' => $_SERVER['REMOTE_ADDR']
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            die('<script>alert("验证码服务暂时不可用，请稍后重试"); window.history.back();</script>');
        }
        
        $json = json_decode($result, true);
        if (!$json || !$json['success']) {
            die('<script>alert("' . TURNSTILE_ERROR_MSG . '"); window.history.back();</script>');
        }
    }
});

// Hook 2: 在页面头部输出脚本和样式
add_hook('ClientAreaHeadOutput', 1, function($vars) {
    if (!TURNSTILE_ENABLED) {
        return '';
    }
    
    $filename = $vars['filename'];
    $showOnPage = false;
    
    // 检查是否应该在当前页面显示
    if ($filename === 'index' && isset($_GET['rp']) && $_GET['rp'] === '/login') {
        $showOnPage = true;
    } elseif (in_array($filename, ['register', 'contact', 'submitticket'])) {
        $showOnPage = true;
    } elseif ($filename === 'cart' && isset($_GET['a']) && $_GET['a'] === 'checkout') {
        $showOnPage = true;
    }
    
    if (!$showOnPage) {
        return '';
    }
    
    return '
<style>
.turnstile-container {
    margin: 20px 0 !important;
    padding: 15px 0 !important;
    text-align: center !important;
    clear: both !important;
    width: 100% !important;
    display: block !important;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 8px;
    border-top: 1px solid rgba(255, 105, 135, 0.1);
    border-bottom: 1px solid rgba(255, 105, 135, 0.1);
}
.cf-turnstile {
    margin: 0 auto !important;
    display: block !important;
}
/* 确保验证码在正确位置显示 */
.turnstile-container + .form-actions,
.turnstile-container + .actions,
.turnstile-container + button,
.turnstile-container + input[type="submit"] {
    margin-top: 15px !important;
}
@media (max-width: 768px) {
    .cf-turnstile {
        transform: scale(0.9);
        transform-origin: center;
    }
    .turnstile-container {
        margin: 15px 0 !important;
        padding: 10px 0 !important;
    }
}
</style>';
});

// Hook 3: 在页面底部输出JavaScript
add_hook('ClientAreaFooterOutput', 1, function($vars) {
    if (!TURNSTILE_ENABLED) {
        return '';
    }
    
    $filename = $vars['filename'];
    $showOnPage = false;
    
    // 检查是否应该在当前页面显示
    if ($filename === 'index' && isset($_GET['rp']) && $_GET['rp'] === '/login') {
        $showOnPage = true;
    } elseif (in_array($filename, ['register', 'contact', 'submitticket'])) {
        $showOnPage = true;
    } elseif ($filename === 'cart' && isset($_GET['a']) && $_GET['a'] === 'checkout') {
        $showOnPage = true;
    }
    
    if (!$showOnPage) {
        return '';
    }
    
    return '
<script>
console.log("Turnstile Hook: 开始加载 - 页面: ' . $filename . '");

function addTurnstileWidget() {
    console.log("Turnstile: 执行添加函数");
    
    // 检查是否已经添加过
    if (document.querySelector(".turnstile-container")) {
        console.log("Turnstile: 验证码已存在");
        return;
    }
    
    // 创建容器
    var container = document.createElement("div");
    container.className = "turnstile-container";
    container.innerHTML = \'<div class="cf-turnstile" data-sitekey="' . TURNSTILE_SITE_KEY . '" data-theme="' . TURNSTILE_THEME . '"></div>\';
    
    var targetForm = null;
    var insertLocation = null;
    
    // 查找表单
    var formSelectors = [
        "form.login-form",
        "form[action*=\"register\"]",
        "form#frmUserRegister", 
        "form[action*=\"contact\"]",
        "#frmSendMessage",
        "form[action*=\"submitticket\"]",
        "#frmOpenTicket",
        "#frmCheckout",
        "form[action*=\"checkout\"]"
    ];
    
    for (var s = 0; s < formSelectors.length; s++) {
        var forms = document.querySelectorAll(formSelectors[s]);
        if (forms.length > 0) {
            targetForm = forms[0];
            console.log("Turnstile: 找到表单: " + formSelectors[s]);
            break;
        }
    }
    
    // 如果没找到特定表单，查找通用表单
    if (!targetForm) {
        var allForms = document.querySelectorAll("form");
        for (var i = 0; i < allForms.length; i++) {
            var form = allForms[i];
            var submitBtn = form.querySelector("button[type=submit], input[type=submit]");
            if (submitBtn) {
                targetForm = form;
                console.log("Turnstile: 找到通用表单");
                break;
            }
        }
    }
    
    if (!targetForm) {
        console.error("Turnstile: 未找到任何表单");
        return;
    }
    
    // 改进的插入策略 - 专门查找提交按钮区域
    var insertStrategies = [
        // 策略1: 查找提交按钮并在其直接前面插入
        function() {
            var submitSelectors = [
                "button[type=submit]",
                "input[type=submit]",
                ".btn-primary[type=submit]",
                ".form-actions button",
                ".actions button",
                ".submit-btn",
                "button[value*=\"注册\"]",
                "button[value*=\"提交\"]",
                "button[value*=\"发送\"]",
                "input[value*=\"注册\"]",
                "input[value*=\"提交\"]",
                "input[value*=\"发送\"]"
            ];
            
            for (var i = 0; i < submitSelectors.length; i++) {
                var submitBtn = targetForm.querySelector(submitSelectors[i]);
                if (submitBtn && targetForm.contains(submitBtn)) {
                    console.log("Turnstile: 找到提交按钮: " + submitSelectors[i]);
                    return {parent: targetForm, before: submitBtn};
                }
            }
            return null;
        },
        
        // 策略2: 查找.form-actions或.actions容器
        function() {
            var actionContainers = targetForm.querySelectorAll(".form-actions, .actions, .submit-area, .btn-group");
            for (var i = 0; i < actionContainers.length; i++) {
                var container = actionContainers[i];
                if (targetForm.contains(container)) {
                    console.log("Turnstile: 找到actions容器");
                    return {parent: targetForm, before: container};
                }
            }
            return null;
        },
        
        // 策略3: 查找表单的最后一个.form-group
        function() {
            var formGroups = targetForm.querySelectorAll(".form-group, .form-row, .row");
            if (formGroups.length > 0) {
                var lastGroup = formGroups[formGroups.length - 1];
                // 确保这个group包含提交按钮或者是最后一个字段组
                var hasSubmit = lastGroup.querySelector("button[type=submit], input[type=submit]");
                if (hasSubmit) {
                    console.log("Turnstile: 找到包含提交按钮的表单组");
                    return {parent: targetForm, before: lastGroup};
                }
            }
            return null;
        },
        
        // 策略4: 在表单的最后一个子元素前插入
        function() {
            var children = targetForm.children;
            if (children.length > 0) {
                var lastChild = children[children.length - 1];
                console.log("Turnstile: 在表单最后一个子元素前插入");
                return {parent: targetForm, before: lastChild};
            }
            return null;
        },
        
        // 策略5: 直接追加到表单末尾
        function() {
            console.log("Turnstile: 追加到表单末尾");
            return {parent: targetForm, before: null};
        }
    ];
    
    // 尝试各种插入策略
    var inserted = false;
    for (var strategy = 0; strategy < insertStrategies.length; strategy++) {
        try {
            insertLocation = insertStrategies[strategy]();
            if (insertLocation) {
                if (insertLocation.before) {
                    // 验证before元素确实是parent的子元素
                    if (insertLocation.parent.contains(insertLocation.before)) {
                        insertLocation.parent.insertBefore(container, insertLocation.before);
                        console.log("Turnstile: 使用策略 " + (strategy + 1) + " 插入成功");
                        inserted = true;
                        break;
                    }
                } else {
                    // 插入到末尾
                    insertLocation.parent.appendChild(container);
                    console.log("Turnstile: 使用策略 " + (strategy + 1) + " 插入到末尾成功");
                    inserted = true;
                    break;
                }
            }
        } catch (e) {
            console.log("Turnstile: 策略 " + (strategy + 1) + " 执行失败: " + e.message);
            continue;
        }
    }
    
    if (inserted) {
        console.log("Turnstile: 验证码插入成功");
        
        // 加载API
        if (!document.querySelector("script[src*=turnstile]")) {
            var script = document.createElement("script");
            script.src = "https://challenges.cloudflare.com/turnstile/v0/api.js";
            script.async = true;
            script.defer = true;
            script.onload = function() {
                console.log("Turnstile: API加载完成");
            };
            script.onerror = function() {
                console.error("Turnstile: API加载失败");
            };
            document.head.appendChild(script);
        }
    } else {
        console.error("Turnstile: 所有插入策略都失败了");
    }
}

// 延迟执行，确保页面完全加载
setTimeout(function() {
    addTurnstileWidget();
}, 1500);

document.addEventListener("DOMContentLoaded", function() {
    setTimeout(addTurnstileWidget, 1000);
});

window.addEventListener("load", function() {
    setTimeout(addTurnstileWidget, 800);
});
</script>';
});

// 调试Hook
add_hook('ClientAreaFooterOutput', 99, function($vars) {
    return '<!-- Turnstile Hook Loaded Successfully -->';
});
