/**
 * 转义
 */
Admin.htmlspecialchars = function(str){
    str = str.replace(/&/g, '&amp;');
    str = str.replace(/</g, '&lt;');
    str = str.replace(/>/g, '&gt;');
    str = str.replace(/"/g, '&quot;');
    str = str.replace(/'/g, '&#039;');
    return str;
};
/**
 * 反转义
 */
Admin.htmlspecialchars_decode = function(str){
    str = str.replace(/&amp;/g, '&');
    str = str.replace(/&lt;/g, '<');
    str = str.replace(/&gt;/g, '>');
    str = str.replace(/&quot;/g, '"');
    str = str.replace(/&#039;/g, "'");
    return str;
};
/**
 * 自动设置表单值
 */
Admin.setValue          = function(name, value){
    var first = name.substr(0,1), input, i = 0, val;
    if(value === "") return;
    if("#" === first || "." === first){
        input = $(name);
    } else {
        input = $("[name='" + name + "']");
    }

    if(input.eq(0).is(":radio")) { //单选按钮
        input.filter("[value='" + value + "']").each(function(){this.checked = true});
    }else if(input.eq(0).is(":checkbox")){ //复选框
        if(!$.isArray(value)){
            val = new Array();
            val[0] = value;
        }else{
            val = value;
        }
        for(i = 0, len = val.length; i < len; i++){
            input.filter("[value='" + val[i] + "']").each(function(){this.checked = true});
        }
    }else{//其他表单选项直接设置值
        input.val(value);
    }
};
/**
 * url动态添加参数
 */
Admin.addUrlVar         = function(u, v){
    u += '';
    if(!!v){
        //v可以是查询字符串,进行直接拼接
        if('string' != typeof v){
            var arr = [];
            var i   = 0;
            for(var k in v){
                arr[i++] = k + '=' + v[k];
            }
            v = arr.join('&');
        }
        if(u.indexOf('?') < 0){
            u += '?' + v;
        }else{
            u = u.replace('?', '?' + v + '&');
        }
    }
    return u;
};
/**
 * 从表单的action中解析出pathinfo
 * 唯一使用情况就是在url模式为普通和兼容情况下的get表单
 */
Admin.getUrlPathInfo    = function(url){
    var depr = Admin.URL_VAR.VAR_DEPR,module = Admin.URL_VAR.VAR_MODULE,controller = Admin.URL_VAR.VAR_CONTROLLER,action = Admin.URL_VAR.VAR_ACTION,pathinfo = Admin.URL_VAR.VAR_PATHINFO;
    var reg = new RegExp('\\b['+ module +'|'+ controller +'|'+ action +'|'+ pathinfo +']=[\\w'+ depr +']+\\b','gi');
    var match = url.match(reg)
    var t;
    if(match){
        url = {};
        for(var i = 0; i < match.length; i++ ){
            t = match[i].split('=');
            url[t[0]] = t[1];
        }
        if(url[pathinfo]){
            return url[pathinfo];
        }else if(url[module] && url[controller] && url[action]){
            return '/'+url[module]+'/'+url[controller]+'/'+url[action]
        }
    }
    return false;
};

/**
 * 标准post表单自动跳转(用于非ajax模式)
 */
Admin.standarPost       = function(url, data){
    var form = $('<form style="display:none;" method="post" action="'+ url +'"></form>');
    //兼容字符串数据
    if('string' == typeof data && '' != data){
        var vars = data.split('&'), t = {}, v;
        for(var i = 0; i < vars.length; i++){
            v = vars[i].split('=');
            t[v[0]] = v[1];
        }
        data = t;
    }
    if(data){
        $.each(data, function(key, value){
            form.append('<input type="hidden" name="'+ key +'" value="'+ value +'" />');
        });
    }
    var token = $('meta[name="'+ Admin.TOKEN_NAME +'"]').attr('content')
    if(token){
        //添加表单令牌
        form.append('<input type="hidden" name="'+ Admin.TOKEN_NAME +'" value="'+ token +'" />');
    }
    form.appendTo(document.body).submit();
};

/**
 * 默认时间初始化
 */
Admin.init              = function(context){
    //get表单事件
    $("form[method!='post']", context).submit(function(){
        //如果表单不是post方式则不提交令牌参数(ajax提交的在后台也会不检测令牌,所以只有纯post方式才会验证令牌)
        $('input[name='+ Admin.TOKEN_NAME +']', this).attr('disabled', true);
        //兼容模式和普通模式中有问号,需要在get表单加入兼容模式隐藏域
        if(Admin.URL_MODEL === '0' || Admin.URL_MODEL === '3'){
            var pathinfo = Admin.getUrlPathInfo($(this).attr('action'));
            if(pathinfo){
                $(this).append('<input type="hidden" name="'+ Admin.URL_VAR.VAR_PATHINFO +'" value="'+ pathinfo +'" />');
            }else{
                Admin.info('PATHINFO解码失败', undefined, undefined);
                return false;
            }
        }
    });
    //post表单必须经过自动验证才能从此处触发(否则将以常规方式提交)
    //todo:常规方式无法做到成功后指定跳转
    $.validator.setDefaults({submitHandler:function(form){
        form = $(form);
        var text = form.attr('data-confirm'), url = form.attr('action'), data = form.serialize(), hint = form.attr('data-hint');
        if(Admin.AJAX_MODE){
            if(text){
                Admin.confirm(text, function(){Admin.post(url, data, hint);return true;});
            }else{
                Admin.post(url, data, hint);
            }
        }else{//一定要用原生的提交,否则会死循环
            if(text){
                Admin.confirm(text, function(){form[0].submit();return true;});
            }else{
                form[0].submit();
            }
        }
    }});

    //ajax-html-dialog按钮绑定
    $('[admintype="html"]', context).click(function(){
        var self = $(this);
        Admin.html(self.attr('data-title'),self.attr('data-width'),self.attr('data-url'),self.attr('data-data'),self.attr('data-target'));
    });

    //ajax-post-dialog按钮绑定
    $('[admintype="post"]', context).click(function(){
        var self = $(this), text = self.attr('data-confirm'), url = self.attr('data-url'), data = self.attr('data-data'), hint = self.attr('data-hint');
        if(Admin.AJAX_MODE) {
            if(text){
                Admin.confirm(text, function(){Admin.post(url, data, hint); return true;});
            }else{
                Admin.post(url, data, hint);
            }
        }else{
            if(text){
                Admin.confirm(text, function(){Admin.standarPost(url, data); return true;});
            }else{
                Admin.standarPost(url, data);
            }
        }
    });

    //radio双击取消选择
    $('input[type="radio"]', context).dblclick(function(){$(this).prop('checked', false);});

    //时间控件
    $('.qtime', context).datepicker().click(function(){$(this).val('')});
    $('.qtimes',context).datetimepicker({
        showHour: true,showMinute: true,showSecond: true,
        timeFormat: 'HH:mm:ss',timeText: '时间',hourText: '时',minuteText: '分',secondText: '秒',currentText: '当前'
    }).click(function(){$(this).val('')});

    //title优化提示
    $('.tip', context).poshytip({className:'tip-yellowsimple',showOn:'hover',alignTo:'target',alignX: 'center',alignY:'top',offsetX:0,offsetY:2,allowTipHover:false});

    //店铺自动完成
    $('input[tool-auto-store]', context).each(function(){
        var input = $(this);
        input.attr('data-id', input.attr('tool-auto-store'));
        Admin.autoStore(input, null, null);
    });

    $('input[tool-auto-app]', context).each(function(){
        var input = $(this);
        input.attr('data-id', input.attr('tool-auto-app'));
        Admin.autoApp(input, null, null);
    });


    //商品自动完成
    $('input[tool-auto-goods]', context).each(function(){
        var input = $(this);
        input.attr('data-id', input.attr('tool-auto-goods'));
        Admin.autoGoods(input, null, null);
    });

    //用户自动完成
    $('input[tool-auto-user]', context).each(function(){
        var input = $(this), id = input.attr('tool-auto-user');

        //有店铺的自动完成框,才进行user_level触发
        var store = $('[name*=store_id]', context);

        if(store.length){
            //上三级按钮(默认隐藏,有默认值或全中时显示)
            var level = Admin.userLevel($('<label class="hover-click"><i class="icon-group"></i></label>').css({display:id?'inline-block':'none'}).click(function(){
                var self = $(this);
                if(self.hasClass('hover')){
                    //还原为hover
                    Admin.userLevel(self, 'store_id='+store.val() + '&user_id=' + id);
                    self.removeClass('hover');
                }else{
                    //切换为click
                    self.addClass('hover').unbind('hover').poshytip('show');
                }
            }).insertAfter(input), 'store_id='+store.val() + '&user_id=' + id).unbind('hover');
        }

        //自动完成回调
        input.attr('data-id', id);
        Admin.autoUser(input, function(event, ui){
            id = ui.item._.user_id;//必须,否则click还原为hover时,id会没有值
            if(store.length){
                Admin.userLevel(level.show(), 'store_id='+store.val() + '&user_id=' + id).unbind('hover');//.click();//默认触发显示上三级
            }
        }, null);
    });

    //上三级显示
    $('[tool-user-level]', context).each(function(){
        Admin.userLevel(this, $(this).attr('tool-user-level'));
    });

    //禁止表单保存数据(错误回跳时不显示),对原始的存在的值无影响
    $('input[type="text"]', context).attr('autocomplete','off');//.click(function(){$(this).select();});
    $('input[type="hidden"]', context).attr('autocomplete','off');

    //图片掠过大图显示
    $('.bigger', context).hover(function(){
        var self = $(this);
        $('#img-bigger').css({zIndex:9999,top:self.offset().top - 20 - $(window).scrollTop(),left:self.offset().left + self.width() + 20}).children().attr('src', self.attr('src')).end().show();
    },function(){
        $('#img-bigger').hide();
    });

    //分页到指定页
    $('.page input', context).keydown(function(e){
        if(13 === e.keyCode){
            $(this).trigger('change');
            return false;
        }
    }).click(function(){
        $(this).val('');
    }).change(function(){
        var v = {}, self = $(this), val = parseInt(self.val());
        v[Admin.URL_VAR.VAR_PAGE] = Math.max(Math.min(isNaN(val)?1:val, self.attr('data-total')), 1);
        location = Admin.addUrlVar($('.page .num').attr('href'), v);
    });

    //图片不存在
    $('img', context).error(function(){
        $(this).attr('src', '/Public/Admin/images/img-error.png');
    });
};

/**
 * 通用多级联动
 * 写的通用,考虑换掉seller的区域三级联动
 * 需要与后台交互数据,关联信息以json数组方式返回
 */
Admin.linkage           = function(target, url, pid, callback){
    //初始值必须是-1,为空有可能被当作一级分类
    var select = $('<select><option value="-1">请选择...</option></select>'), stop = true;
    //包装第一次进入的位置插入标记
    if('string' === typeof target){
        target = $(target);
    }
    //优先获取数据,当返回空数组或发生错误时停止递归
    $.getJSON(url, {'pid':pid}, function(data){
        $.each(data, function(index, obj){
            select.append('<option value="'+ obj.id +'">'+ obj.name  +'</option>');
        });
        stop = !data.length;
    }).error(function(){
        select.children().text('-'+ Admin.LANG.MSG_REMOTE_ERROR +'-');
        stop = false;
    }).complete(function(){
        if(!stop){ select.show();}
    });
    //利用递归实现,每次进入都是一次全新的初始化,只是插入位置和请求ID发生变化而已
    select.change(function(){
        var self = $(this);
        if(callback && !callback.call(self, self.val(), target.parent().children('select').index(self))){
            //回调参数[当前select元素jquery对象, 当前select选中值, 当前select在所有select中的位置]
            //有回调,并且回调返回假值则终止递归
            return;
        }
        //清空当前之后,准备[初始化]
        self.nextAll('select').remove();
        //初始化位置为当前,请求ID,为当前ID
        Admin.linkage(self, url, self.val(), callback);
    });
    //由于ajax是异步的,新建的select先不显示,等异步判断完成后,在决定是否显示
    target.after(select.hide());
};

/**
 * 显示用户上三级关联
 * @param element
 * @param params
 * @returns {*}
 */
Admin.userLevel         = function(element, params){
    if(!(element instanceof jQuery)) element = $(element);
    return element.poshytip('destroy').poshytip({className:'tip-yellowsimple',showOn:'none',alignTo:'target',alignX: 'center',alignY:'top',offsetX:0,offsetY:2,allowTipHover:false})
        .poshytip('update', function(updateCallback){
            if(params){
                if(Admin.userLevel.cache[params]){
                    return Admin.userLevel.cache[params];
                }else{
                    var title = '';
                    $.getJSON(Admin.URL_USER_LEVEL, params, function(data){
                        $.each(data, function(i, e){
                            title += '<tr><td><p>LV.'+ (data.length - i - 1) +'</p></td><td><p>'+ e.store_id  +'</p></td><td><p>'+ e.user_id +'</p></td><td><p>'+ (e.user_name?e.user_name:'') +'</p></td><td><p>'+ (e.true_name?e.true_name:'') +'</p></td><td><p>'+ (e.mobile_phone?e.mobile_phone:'') +'</p></td><td><p>'+ (e.email?e.email:'') +'</p></td><td><p>'+ (e.rank_name?e.rank_name:'') +'</p></td><td><p>'+ (0 == e.is_vip?'<i class="icon-remove" style="color: blue;"></i>':(0 < e.is_vip ? '<i class="icon-ok" style="color: green;"></i>' : '<i class="icon-minus-sign" style="color: red;"></i>')) +'</p></td></tr>';
                        });
                        title = data.length ? '<div class="table"><table><thead><tr style="height: 30px;"><th><p>层级</p></th><th><p>店铺</p></th><th><p>ID</p></th><th><p>用户名</p></th><th><p>姓名</p></th><th><p>手机</p></th><th><p>邮箱</p></th><th><p>等级</p></th><th><p>VIP</p></th></tr></thead><tbody>'+ title +'</tbody></table></div>' : '没有上级用户';
                        Admin.userLevel.cache[params] = title
                    }).error(function(){
                        //失败不缓存
                        title = Admin.LANG.MSG_REMOTE_ERROR;
                    }).complete(function(){
                        //更新数据
                        updateCallback(title);
                    });
                    return '加载中...'
                }
            }else{
                return '必须指定一个用户';
            }
    }).unbind('hover').hover(function(){
        $(this).poshytip('show');
    },function(){
        $(this).poshytip('hide');
    });
};
Admin.userLevel.cache   = [];
/**
 * App自动完成
 * @param input
 * @param select
 * @param foucs
 * @returns {*}
 */
Admin.autoApp=function(input,select,focus){
    if(!(input instanceof jQuery)) input = $(input);

    //表单结构初始化(关键在id的区分,自动化时用tool-auto-store传data-id)
    var name = input.attr('data-name'), id = input.attr('data-id'), hidden;

    //默认属性
    id      = id ? id : '';
    name    = name ? name : 'app_id';//默认隐藏域名称
    if(id) input.prop('readonly', true);
    input.attr('placeholder', 'APP-ID,APP名称');

    //构造隐藏域
    if(!input.prev('input[type="hidden"][name="'+ name +'"]').length){
        //不存在插入隐藏域
        input.before('<input type="hidden" name="'+ name +'">');
    }
    hidden  = input.prev('input[type="hidden"][name="'+ name +'"]').val(id);

    //单机重置可编辑状态
    input.click(function(){
        hidden.val('');
        input.val('').prop('readonly', false);
        $('input[tool-auto-app]').click();
    });

    //自动完成初始化
    input.autocomplete({
        source  : Admin.URL_AUTO_APP,
        delay   : 500,
        select  : function(event, ui){

            //默认input灰色只读,填充隐藏域ID
            input.prop('readonly', true).prev().val(ui.item._.app_id);

            if(select){
                return select.call(this, event, ui);
            }else{
                //不阻止默认事件(label填充)
                return true;
            }
        },
        focus   : function(event, ui){
            //默认按label设置,回调函数可改(必须设置,否则移动鼠标时不会实时改变)
            $(event.target).val(ui.item.label);
            if(foucs){
                foucs.call(this, event, ui);
            }
            //总是阻止默认的填充事件
            return false;
        }
    });
    //下拉列表扩展
    input.data('autocomplete')._renderItem = function(ul, item){
        //自定义下拉列表(注意:两处data函数中的参数很重要,纠结了半天)
        return $('<li>').data('item.autocomplete', item)
            .append('<a>['+ item.id +']'+ item.appname +'</a>')
            .appendTo(ul);
    };
    //应答回调(可修改ajax传回的数据供显示)
    input.data('autocomplete').__response = function(data){
        if(!this.options.disabled && data && data.length){
            //原底层代码
            data = this._normalize(data);
            this._suggest(data);
            this._trigger('open');
        }else{
            //这里仅用于当没有数据时提示
            $(this.element).val(data.msg ? data.msg : '未找到匹配');
            this.close();
        }
    };
    return input;
}
/**
 * 店铺自动完成
 * @param input
 * @param select
 * @param foucs
 * @returns {*}
 */
Admin.autoStore         = function(input, select, foucs){
    if(!(input instanceof jQuery)) input = $(input);

    //表单结构初始化(关键在id的区分,自动化时用tool-auto-store传data-id)
    var name = input.attr('data-name'), id = input.attr('data-id'), hidden;

    //默认属性
    id      = id ? id : '';
    name    = name ? name : 'store_id';//默认隐藏域名称
    if(id) input.prop('readonly', true);
    input.attr('placeholder', '店铺ID,店铺名称');

    //构造隐藏域
    if(!input.prev('input[type="hidden"][name="'+ name +'"]').length){
        //不存在插入隐藏域
        input.before('<input type="hidden" name="'+ name +'">');
    }
    hidden  = input.prev('input[type="hidden"][name="'+ name +'"]').val(id);

    //单机重置可编辑状态
    input.click(function(){
        hidden.val('');
        input.val('').prop('readonly', false);
        $('input[tool-auto-user]').click();
    });

    //自动完成初始化
    input.autocomplete({
        source  : Admin.URL_AUTO_STORE,
        delay   : 500,
        select  : function(event, ui){

            //默认input灰色只读,填充隐藏域ID
            input.prop('readonly', true).prev().val(ui.item._.store_id);

            if(select){
                return select.call(this, event, ui);
            }else{
                //不阻止默认事件(label填充)
                return true;
            }
        },
        focus   : function(event, ui){
            //默认按label设置,回调函数可改(必须设置,否则移动鼠标时不会实时改变)
            $(event.target).val(ui.item.label);
            if(foucs){
                foucs.call(this, event, ui);
            }
            //总是阻止默认的填充事件
            return false;
        }
    });
    //下拉列表扩展
    input.data('autocomplete')._renderItem = function(ul, item){
        //自定义下拉列表(注意:两处data函数中的参数很重要,纠结了半天)
        return $('<li>').data('item.autocomplete', item)
            .append('<a>['+ item.store_id +']'+ item.store_name +'</a>')
            .appendTo(ul);
    };
    //应答回调(可修改ajax传回的数据供显示)
    input.data('autocomplete').__response = function(data){
        if(!this.options.disabled && data && data.length){
            //原底层代码
            data = this._normalize(data);
            this._suggest(data);
            this._trigger('open');
        }else{
            //这里仅用于当没有数据时提示
            $(this.element).val(data.msg ? data.msg : '未找到匹配');
            this.close();
        }
    };
    return input;
};

/**
 * 用户自动完成
 * @param input
 * @param select
 * @param foucs
 * @returns {*}
 */
Admin.autoUser          = function(input, select, foucs){
    if(!(input instanceof jQuery)) input = $(input);

    //表单结构初始化(关键在id的区分,自动化时用tool-auto-user传data-id)
    var name = input.attr('data-name'), id = input.attr('data-id'), hidden;

    //默认属性
    id      = id ? id : '';
    name    = name ? name : 'user_id';//默认隐藏域名称
    if(id) input.prop('readonly', true);
    input.attr('placeholder', '用户ID,用户名,姓名,手机,邮箱');

    //构造隐藏域
    if(!input.prev('input[type="hidden"][name="'+ name +'"]').length){
        //不存在插入隐藏域
        input.before('<input type="hidden" name="'+ name +'">');
    }
    hidden  = input.prev('input[type="hidden"][name="'+ name +'"]').val(id);

    //单击重置可编辑状态
    input.click(function(){
        hidden.val('');
        input.val('').prop('readonly', false).next().removeClass('hover').hide().poshytip('hide');
    });

    //自动完成初始化
    input.autocomplete({
        source  : Admin.URL_AUTO_USER,
        delay   : 500,
        select  : function(event, ui){

            //默认input灰色只读,填充隐藏域ID
            input.prop('readonly', true).prev().val(ui.item._.user_id);

            if(select){
                return select.call(this, event, ui);
            }else{
                //不阻止默认事件(label填充)
                return true;
            }
        },
        focus   : function(event, ui){
            //默认按label设置,回调函数可改(必须设置,否则移动鼠标时不会实时改变)
            $(event.target).val(ui.item.label);
            if(foucs){
                foucs.call(this, event, ui);
            }
            //总是阻止默认的填充事件
            return false;
        }
    });
    //下拉列表扩展
    input.data('autocomplete')._renderItem = function(ul, item){
        //自定义下拉列表(注意:两处data函数中的参数很重要,纠结了半天)
        return $('<li>').data('item.autocomplete', item)
            .append('<a>' +
                        '<dl class="ui-autocomplete-user">' +
                            '<dt><img src="'+ item.url +'"><p>'+ item.true_name +'</p></dt>' +
                            '<dd>' +
                                '<p class="username"><i class="icon-user"></i>['+ item.user_id + ']'+ item.user_name +'</p>' +
                                '<p class="phone"><i class="icon-phone-sign"></i>'+ item.mobile_phone +'</p>' +
                                '<p class="email"><i class="icon-twitter"></i>'+ item.email +'</p>' +
                            '</dd>' +
                        '</dl>' +
                    '</a>')
            .appendTo(ul);
    };
    //多出滚动条长宽扩展
    input.data('autocomplete')._resizeMenu = function(){
        this.menu.element.css('max-height','315px');
        this.menu.element.outerWidth(Math.max(this.menu.element.width("").outerWidth() + 20, this.element.outerWidth()))
    };
    //应答回调(可修改ajax传回的数据供显示)
    input.data('autocomplete').__response = function(data){
        if(!this.options.disabled && data && data.length){
            //原底层代码
            data = this._normalize(data);
            this._suggest(data);
            this._trigger('open');
        }else{
            //这里仅用于当没有数据时提示
            $(this.element).val(data.msg ? data.msg : '未找到匹配');
            this.close();
        }
    };
    return input
};

/**
 * 商品自动完成
 * @param input
 * @param select
 * @param foucs
 * @returns {*}
 */
Admin.autoGoods          = function(input, select, foucs){
    if(!(input instanceof jQuery)) input = $(input);

    //表单结构初始化(关键在id的区分,自动化时用tool-auto-goods传data-id)
    var name = input.attr('data-name'), id = input.attr('data-id'), store = input.attr('data-store'), hidden;

    //默认属性
    id      = id ? id : '';
    name    = name ? name : 'goods_id';//默认隐藏域名称
    if(id) input.prop('readonly', true);
    input.attr('placeholder', '商品ID,商品GSN,商品名称');

    //构造隐藏域
    if(!input.prev('input[type="hidden"][name="'+ name +'"]').length){
        //不存在插入隐藏域
        input.before('<input type="hidden" name="'+ name +'">');
    }
    hidden  = input.prev('input[type="hidden"][name="'+ name +'"]').val(id);

    //单击重置可编辑状态
    input.click(function(){
        hidden.val('');
        input.val('').prop('readonly', false).next().hide().poshytip('hide');
    });

    //自动完成初始化
    input.autocomplete({
        source  : Admin.addUrlVar(Admin.URL_AUTO_GOODS, {store_id : store}),
        delay   : 500,
        select  : function(event, ui){

            //默认input灰色只读,填充隐藏域ID
            input.prop('readonly', true).prev().val(ui.item._.goods_id);

            if(select){
                return select.call(this, event, ui);
            }else{
                //不阻止默认事件(label填充)
                return true;
            }
        },
        focus   : function(event, ui){
            //默认按label设置,回调函数可改(必须设置,否则移动鼠标时不会实时改变)
            $(event.target).val(ui.item.label);
            if(foucs){
                foucs.call(this, event, ui);
            }
            //总是阻止默认的填充事件
            return false;
        }
    });
    //下拉列表扩展
    input.data('autocomplete')._renderItem = function(ul, item){
        //自定义下拉列表(注意:两处data函数中的参数很重要,纠结了半天)
        return $('<li>').data('item.autocomplete', item)
            .append('<a>['+ item.goods_sn +']'+ item.goods_name +'</a>')
            .appendTo(ul);
    };
    //应答回调(可修改ajax传回的数据供显示)
    input.data('autocomplete').__response = function(data){
        if(!this.options.disabled && data && data.length){
            //原底层代码
            data = this._normalize(data);
            this._suggest(data);
            this._trigger('open');
        }else{
            //这里仅用于当没有数据时提示
            $(this.element).val(data.msg ? data.msg : '未找到匹配');
            this.close();
        }
    };
    return input
};





/**
 * 关于跳转
 * 只有html和post可以产生跳转
 * [html]成功显示显然无需跳转,失败时根据后台是否跳转而跳转
 * [post]失败时不跳转(一般情况下没有必要),成功时按后台跳转(彻底去掉jump跳转)
 */

/**
 * 确认对话框
 */
Admin.confirm          = function(text, callback){
    var options = {
        'type'          : 'confirm',
        'text'          : text,
        'yes_callback'  : callback,
        'no_coallback'  : undefined
    };
    return Dialog.get('confirm_dialog_id', Admin.LANG, undefined).setWidth(402).setTitle('提示信息').setContents('message',options).show('center');
};

/**
 * ajax获取html生成对话框
 */
Admin.html             = function(title, width, url, data, target, id){
    var options = {
        'url'       : url,
        'data'      : data,
        'itime'     : undefined,
        'etime'     : 3,
        'icallback' : undefined,
        'ecallback' : function(data){
            if(data.url){
                location.href = data.url;
                //return true;//直接跳转,不再显示
            }else{
                return true;
            }
        },
        'target'    : target
    };
    //支持多层对话框时,需要提供新的ID名称
    id = id ? id : 'html_dialog_id';
    if(url){
        return Dialog.get(id, Admin.LANG, Admin.init).setWidth(width).setTitle(title).setContents('html',options).show('center');
    }else{
        return Dialog.get(id, Admin.LANG, Admin.init).setWidth(width).setTitle(title).setContents(data).show('center');
    }
};

/**
 * 特殊的相册对话框
 */
Admin.album             = function(callback, id, url, close){
    //只能用全局回调,如果通过上面的option传回调,在相册内部的操作就无法继续传送原始的回调
    Admin.album.callback    = callback ? callback : Admin.album.callback;
    Admin.album.close       = close ? close : Admin.album.close;
    Admin.html('打开相册', 650, (url ? url : Admin.URL_ALBUM), (id ? id : undefined), undefined, 'html_album_id');
};

/**
 * ajax-post返回结果对话框
 */
Admin.post             = function(url, data, hint, callback){

    $('.hover-click').poshytip('hide').removeClass('hover');

    var options = {
        'url'       : url,
        'data'      : data,
        'itime'     : undefined,
        'stime'     : hint?0:2,
        'etime'     : 3,
        'icallback' : undefined,
        'scallback' : callback ? callback : function(data){
            if(data.url){
                location.href = data.url;
            }else{
                location.reload(true);
            }
            //return true;//直接跳转,不再显示
        },
        'ecallback' : undefined
    };
    return Dialog.get('post_dialog_id', Admin.LANG, undefined).setWidth(402).setTitle('提示信息').setContents('post',options).show('center');
};

/**
 * ajax-json返回json结果供回调函数执行
 */
Admin.json             = function(url, data, callback){
    var options = {
        'url'       : url,
        'data'      : data,
        'itime'     : undefined,
        'stime'     : undefined,//延迟执行回调的时间
        'etime'     : 3,
        'icallback' : undefined,
        'scallback' : callback,
        'ecallback' : undefined
    };
    return Dialog.get('json_dialog_id', Admin.LANG, undefined).setWidth(402).setTitle('提示信息').setContents('json', options).show('center');
};

/**
 * 一般信息提示框
 */
Admin.info             = function(text, time, callback){
    var options = {
        'type'      : 'info',
        'text'      : text,
        'time'      : time,
        'callback'  : callback
    };
    return Dialog.get('info_dialog_id', Admin.LANG, undefined).setWidth(402).setTitle('提示信息').setContents('message',options).show('center');
};

/**
 * 全局初始化
 */
$(function(){

    //左下角工具栏滚动[回顶部]
    (function (btnId){
        var btn=document.getElementById(btnId);
        var scrollTop = document.documentElement.scrollTop || document.body.scrollTop;
        window.onscroll=set;
        btn.onclick=function (){
            btn.style.display="none";
            window.onscroll=null;
            this.timer=setInterval(function(){
                scrollTop = document.documentElement.scrollTop || document.body.scrollTop;
                scrollTop-=Math.ceil(scrollTop*0.1);
                if(scrollTop==0) clearInterval(btn.timer, window.onscroll=set);
                if (document.documentElement.scrollTop > 0) document.documentElement.scrollTop=scrollTop;
                if (document.body.scrollTop > 0) document.body.scrollTop=scrollTop;
            },10);
        };
        function set(){
            scrollTop = document.documentElement.scrollTop || document.body.scrollTop;
            btn.style.display=scrollTop?'block':"none";
        }
    }('gotop'));

    //左下角工具栏滚动[查询]
    $('#query').click(function(){
        $.cookie('query', $('.query-form').toggle().css('display'));
    });
    $('.query-form').css('display', $.cookie('query'));

    //查询工具栏上的导出操作
    $('.query-form .export').click(function(){
        var self = $(this);
        Admin.html('导出字段选择', '480', self.attr('data-url'), self.parents('form').serialize());
    });

    //默认事件绑定初始化
    Admin.init();

});


