//转义
Seller.htmlspecialchars = function(str){
    str = str.replace(/&/g, '&amp;');
    str = str.replace(/</g, '&lt;');
    str = str.replace(/>/g, '&gt;');
    str = str.replace(/"/g, '&quot;');
    str = str.replace(/'/g, '&#039;');
    return str;
};
//反转义
Seller.htmlspecialchars_decode = function(str){
    str = str.replace(/&amp;/g, '&');
    str = str.replace(/&lt;/g, '<');
    str = str.replace(/&gt;/g, '>');
    str = str.replace(/&quot;/g, '"');
    str = str.replace(/&#039;/g, "'");
    return str;
};
//tabs选中
Seller.setStatus        = function(jqObj, value, cls_add, cls_remove, s){
    jqObj.each(function(){
        var obj = $(this);
        if(obj.attr('data-status') === value){//进来的都是字符串,所以可以进行严格比较
            obj.addClass(cls_add).removeClass(cls_remove);
            if(s) return false;
        }
    });
};

//ajax-html-dialog按钮绑定
/*$('[admintype="html"]', context).click(function(){
    var self = $(this);
    Admin.html(self.attr('data-title'),self.attr('data-width'),self.attr('data-url'),self.attr('data-data'),self.attr('data-target'));
});*/

//url动态添加参数
Seller.addUrlVar        = function(u, v){
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
//从表单的action中解析出pathinfo,唯一使用情况就是在url模式为普通和兼容情况下的get表单
Seller.getUrlPathInfo   = function(url){
    var depr = Seller.URL_VAR.VAR_DEPR,module = Seller.URL_VAR.VAR_MODULE,controller = Seller.URL_VAR.VAR_CONTROLLER,action = Seller.URL_VAR.VAR_ACTION,pathinfo = Seller.URL_VAR.VAR_PATHINFO;
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
//自动设置表单值
Seller.setValue         = function(name, value){
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
//标准post表单自动跳转(用于非ajax模式)
Seller.standarPost = function(url, data){
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
    var token = $('meta[name="'+ Seller.TOKEN_NAME +'"]').attr('content')
    if(token){
        //添加表单令牌
        form.append('<input type="hidden" name="'+ Seller.TOKEN_NAME +'" value="'+ token +'" />');
    }
    form.appendTo(document.body).submit();
};
//表单提交事件
Seller.setSubmit        = function(context){
    //get表单事件
    $("form[method!='post']", context).submit(function(){
        //如果表单不是post方式则不提交令牌参数(ajax提交的在后台也会不检测令牌,所以只有纯post方式才会验证令牌)
        $('input[name='+ Seller.TOKEN_NAME +']', this).attr('disabled', true);
        //兼容模式和普通模式中有问号,需要在get表单加入兼容模式隐藏域
        if(Seller.URL_MODEL === '0' || Seller.URL_MODEL === '3'){
            var pathinfo = Seller.getUrlPathInfo($(this).attr('action'));
            if(pathinfo){
                $(this).append('<input type="hidden" name="'+ Seller.URL_VAR.VAR_PATHINFO +'" value="'+ pathinfo +'" />');
            }else{
                Seller.infoDialog('PATHINFO解码失败');
                return false;
            }
        }
    });
    //post表单必须经过自动验证才能从此处触发(否则将以常规方式提交)
    //todo:常规方式无法做到成功后指定跳转
    //todo:和postDilog合并
    $.validator.setDefaults({submitHandler:function(form){
        form = $(form);
        var text = form.attr('data-confirm'), url = form.attr('action'), data = form.serialize(), jump = form.attr('data-jump'), hint = form.attr('data-hint');
        if(Seller.AJAX_MODE){
            if(text){
                Seller.confirmDialog(text, function(){Seller.postDialog(url, data, jump, hint);return true;});
            }else{
                Seller.postDialog(url, data, jump, hint);
            }
        }else{//一定要用原生的提交,否则会死循环
            if(text){
                Seller.confirmDialog(text, function(){form[0].submit();return true;});
            }else{
                form[0].submit();
            }
        }
    }});

    //时间控件
    $('.qtime', context).datepicker({dateFormat: 'yy-mm-dd'}).click(function(){$(this).val('')});

    //禁止表单保存数据(错误回跳时不显示),对原始的存在的值无影响
    $('input[type="text"]', context).attr('autocomplete','off');
    $('input[type="hidden"]', context).attr('autocomplete','off');

    //title优化提示
    $('.tip', context).poshytip({className:'tip-yellowsimple',showOn:'hover',alignTo:'target',alignX: 'center',alignY:'top',offsetX:0,offsetY:2,allowTipHover:false});

    //ajax-html-dialog按钮绑定
    $('[sellertype="htmlDialog"]', context).click(function(){
        var self = $(this);
        Seller.htmlDialog(self.attr('data-title'),self.attr('data-width'),self.attr('data-url'),self.attr('data-data'),self.attr('data-target'));
    });

    //ajax-post-dialog按钮绑定
    $('[sellertype="postDialog"]', context).click(function(){
        var self = $(this), text = self.attr('data-confirm'), url = self.attr('data-url'), data = self.attr('data-data'), jump = self.attr('data-jump'), hint = self.attr('data-hint');
        if(Seller.AJAX_MODE) {
            if(text){
                Seller.confirmDialog(text, function(){Seller.postDialog(url, data, jump, hint); return true;});
            }else{
                Seller.postDialog(url, data, jump, hint);
            }
        }else{
            if(text){
                Seller.confirmDialog(text, function(){Seller.standarPost(url, data); return true;});
            }else{
                Seller.standarPost(url, data);
            }
        }
    });

    //商品自动完成
    $('input[tool-auto-goods]', context).each(function(){
        var input = $(this);
        input.attr('data-id', input.attr('tool-auto-goods'));
        Seller.autoGoods(input, null, null);
    });

    //分页到指定页
    $('._pagination input', context).keydown(function(e){
        if(13 === e.keyCode){
            $(this).trigger('change');
            return false;
        }
    }).click(function(){
        $(this).val('');
    }).change(function(){
        var v = {}, self = $(this), val = parseInt(self.val());
        v[Seller.URL_VAR.VAR_PAGE] = Math.max(Math.min(isNaN(val)?1:val, self.attr('data-total')), 1);
        location = Seller.addUrlVar($('._pagination .num').attr('href'), v);
    });

    //图片不存在
    $('img', context).error(function(){
        $(this).attr('src', '/Public/Seller/images/img-error.png');
    });
};
//树形结构选中,并将结果赋值到指定位置
Seller.setCheck         = function(prefix, name, target){
    //todo:未完成难点[当子集没有全部选中时,要清空父级选中]
    $("input[type='checkbox']["+ name +"^='"+ prefix +"']").click(function(){
        var full_name = $(this).attr(name), checked = $(this).attr('checked');
        full_name = full_name.split('_');
        $("input[type='checkbox']["+ name +"^='"+ prefix + "_" + full_name[2] +"'][checked!="+ checked +"]").click();
    });
};
//通用多级联动
Seller.linkage          = function(target, url, pid, callback){
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
        Seller.linkage(self, url, self.val(), callback);
    });
    //由于ajax是异步的,新建的select先不显示,等异步判断完成后,在决定是否显示
    target.after(select.hide());
};
//区域三级联动,需要与后台交互数据,区域信息以json数组方式返回
//todo:被通用多级联动取代
Seller.regionLinkage    = function(target, url, pid){

    var select = $('<select name="region[]"><option value="-1">-请选择-</option></select>'), stop = true;

    //包装第一次进入的位置插入标记
    if('string' === typeof target){
        target = $(target);
    }

    //优先获取数据,当返回空数组或发生错误时停止递归
    $.getJSON(url, {'pid':pid}, function(data){
        if('0' === data.error){
            $.each(data.data, function(index, obj){
                select.append('<option value="'+ obj.region_id +'">'+ obj.region_name  +'</option>');
            });
            stop = !data.data.length;
        }else{
            select.children().text('-'+ data.msg +'-');
        }
    }).error(function(){
        select.children().text('-'+ Seller.MSG_REMOTE_ERROR +'-');
    }).complete(function(){
        if(!stop){
            select.show();
        }
    });//todo:服务器404错误或者返回错误值时,实际是无法显示的(这里暂不考虑)

    //利用递归实现,每次进入都是一次全新的初始化,只是插入位置和请求ID发生变化而已
    select.change(function(){
        //清空当前之后,准备[初始化]
        $(this).nextAll('select').remove();
        //初始化位置为当前,请求ID,为当前ID
        Seller.regionLinkage($(this), url, $(this).val());

    });
    //由于ajax是异步的,新建的select先不显示,等异步判断完成后,在决定是否显示
    target.after(select.hide());
};

/**
 * ajax-post返回结果对话框
 */
Seller.post             = function(url, data, hint, callback){

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
    return Dialog.get('post_dialog_id', Seller.LANG, undefined).setWidth(402).setTitle('提示信息').setContents('post',options).show('center');
};


//商品自动完成
Seller.autoGoods         = function(input, select, foucs){
    if(!(input instanceof jQuery)) input = $(input);

    //表单结构初始化(关键在id的区分,自动化时用tool-auto-goods传data-id)
    var name = input.attr('data-name'), id = input.attr('data-id'), hidden;

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
        source  : Seller.URL_AUTO_GOODS,
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
 * 特殊的相册对话框
 */
Seller.album             = function(callback, id, url, close){
    //只能用全局回调,如果通过上面的option传回调,在相册内部的操作就无法继续传送原始的回调
    Seller.album.callback    = callback ? callback : Seller.album.callback;
    Seller.album.close       = close ? close : Seller.album.close;
    Seller.html('打开相册', 650, (url ? url : Seller.URL_ALBUM), (id ? id : undefined), undefined, 'html_album_id');
};
/**
 * ajax获取html生成对话框
 */
Seller.html             = function(title, width, url, data, target, id){
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
        return Dialog.get(id, Seller.LANG, Seller.init).setWidth(width).setTitle(title).setContents('html',options).show('center');
    }else{
        return Dialog.get(id, Seller.LANG, Seller.init).setWidth(width).setTitle(title).setContents(data).show('center');
    }
};

//屏幕锁定(一定要配合对话框一起使用,不可单独使用,否则多层对话框时会有逻辑问题)
Seller.screenLocker     = {
    'zIndex'    : 999,//外部总是从这里设置拿获取值(默认值动态生成比对)
    'style'     : {'position':'absolute','top':'0px','left':'0px','backgroundColor':'#000','opacity':0.1},
    'masker'    : null,
    'lock'      : function(){
        if(this.masker === null){
            //生成遮蔽层
            this.style.zIndex = this.zIndex - 2;//初次创建记录默认值
            this.masker = $('<div id="dialog_manage_screen_locker"></div>').css(this.style);
        }
        this.masker.css('zIndex', this.zIndex);
        //占整屏
        this.masker.width($(document).width()).height($(document).height());
        //插入文档
        $(document.body).append(this.masker);
        this.masker.show();
    },
    'unlock'    : function(){
        if(this.masker !== null){
            this.zIndex  = this.zIndex - 2;
            if(this.style.zIndex == this.zIndex){
                //回到最初记录的默认值,进行删除操作
                this.masker.remove();
                this.masker = null;
            }else{
                this.masker.css('zIndex', this.zIndex);
            }
        }
    }
};
//对话框构造函数
Seller.Dialog           = function(id){
    //对话框内存标识符(this.id保存好像没什么用)
    this.id = id;
    //初始化结构设置
    this.dom                = {'wrapper':null,'body':null,'head':null,'title':null,'close_button':null,'content':null};
    this.dom.wrapper        = $('<div id="fwin_' + this.id + '" class="dialog_wrapper"></div>').get(0);
    this.dom.body           = $('<div class="dialog_body"></div>').get(0);
    this.dom.head           = $('<h3 class="dialog_head"></h3>').get(0);
    this.dom.title          = $('<span class="dialog_title_icon"></span>').get(0);
    this.dom.close_button   = $('<span class="dialog_close_button">X</span>').get(0);
    this.dom.content        = $('<div class="dialog_content"></div>').get(0);
    //组合
    $(this.dom.head).append($('<span class="dialog_title"></span>').append(this.dom.title)).append(this.dom.close_button);
    $(this.dom.body).append(this.dom.head).append(this.dom.content);
    $(this.dom.wrapper).append(this.dom.body).append('<div style="clear:both; display:block;"></div>');
    //基本样式(默认隐藏,需要手动显示)
    $(this.dom.wrapper).css({
        'zIndex'            : 0,//正在显示的对话框永远比遮蔽层高1,自动增减
        'display'           : 'none',
        'position'          : 'absolute'
    });
    $(this.dom.body).css({
        'position'          : 'relative'
    });
    $(this.dom.head).css({
        'cursor'            : 'move'
    });
    $(this.dom.content).css({
        'margin'            : '0px',
        'padding'           : '0px'
    });

    var self = this;//不再同一个作用域中
    $(this.dom.close_button).click(function(){
        //对话框默认关闭事件,可覆盖
        self.hide();
    });
    $(this.dom.wrapper).draggable({
        //对话框头部可拖动
        'handle' : this.dom.head
    });
    //放入文档流中
    $(document.body).append(this.dom.wrapper);
};
//设置对话框原型
Seller.Dialog.prototype = {
    'id'            : null,
    'dom'           : null,
    'lastPos'       : null,

    //显示对话框(并没有删除对话框,用zIndex属性标记删除)
    'show'          : function(pos){
        if(pos) this.setPosition(pos);
        //显示状态的正常对话框不在显示,以免造成多层次遮盖层结构异常
        if($(this.dom.wrapper).css('zIndex') > 0) return;

        //模态
        var lock = Seller.screenLocker.zIndex;
        Seller.screenLocker.zIndex = lock + 2;
        $(this.dom.wrapper).css({'zIndex':lock + 3});
        Seller.screenLocker.lock();

        //隐藏下层对话框
        $("div[id^='fwin_'][style*='z-index: "+ (Seller.screenLocker.zIndex - 1) +"']").hide();
        $(this.dom.wrapper).show();
        return this;
    },

    //隐藏对话框
    'hide'          : function(){
        //清除各种状态
        var self = this;
        $(this.dom.wrapper).css({'zIndex':0}).hide();
        $(this.dom.title).html(Seller.LANG.MSG_FATAL_ERROR+(Seller.APP_DEBUG?'(对话框已[删除])':''));
        $(this.dom.content).html('');
        $(this.dom.close_button).unbind('click').click(function(){self.hide();});
        Seller.screenLocker.unlock();

        //还原下层对话框
        $("div[id^='fwin_'][style*='z-index: "+ (Seller.screenLocker.zIndex + 1) +"']").show();
        return this;
    },

    //设置对话框位置
    'setPosition'   : function(pos){
        this.lastPos = pos;
        if(typeof(pos) == 'string'){
            switch(pos){
                case 'center':
                    var left = 0;
                    var top  = 0;
                    var dialog_width    = $(this.dom.wrapper).width();
                    var dialog_height   = $(this.dom.wrapper).height();
                    left = $(window).scrollLeft() + ($(window).width() - dialog_width) / 2;
                    top  = $(window).scrollTop()  + ($(window).height() - dialog_height) / 2;
                    $(this.dom.wrapper).css({left:left + 'px', top:top + 'px'});
                    break;
                case 'top':
                    left = $(window).scrollLeft() + ($(window).width() - $(this.dom.wrapper).width()) / 2;
                    $(this.dom.wrapper).css({left:left + 'px', top:'80px'});
                    break;
            }
        }else if(typeof(pos) == 'object'){
            //{'left':xxx, 'right':xxx}对象
            $(this.dom.wrapper).css(pos);
        }
        return this;
    },

    //设置标题
    'setTitle'      : function(title){
        $(this.dom.title).html(title);
        return this;
    },

    //设置对话框样式
    'setStyle'      : function(style){
        if(typeof(style) == 'object'){
            //css对象名
            $(this.dom.wrapper).css(style);
        }else{
            //样式类名
            $(this.dom.wrapper).addClass(style);
        }
        return this;
    },

    //宽高设置
    'setWidth'      : function(width){
        this.setStyle({'width' : width + 'px'});
        return this;
    },
    'setHeight'     : function(height){
        this.setStyle({'height' : height + 'px'});
        return this;
    },

    //设置内容
    'setContents'   : function(type, options){
        //首先通过不同方式创建内容(核心方法)
        var contents = this.createContents(type, options);
        if(typeof(contents) == 'string'){
            //字符串
            $(this.dom.content).html(contents);
        }else{
            //html对象
            $(this.dom.content).empty();
            $(this.dom.content).append(contents);
        }
        return this;
    },

    //多种方式生成对话框内容
    //没有options,直接显示type
    //type[html],options[url,data,itime,icallback,etime,ecallback,target]//成功直接插入
    //type[post],options[url,data,itime,icallback,stime,scallback,etime,ecallback]//根据返回值生成提示对话框error,success
    //type[json],options[url,data,itime,icallback,stime,scallback,etime,ecallback]//参数同post,只是在成功时是直接执行,没有对话框显示
    //type[loading],options显示字符
    //type[message],options[text,type[success/error/info(time,callback,data<可以从服务器获取数据传参>),confirm([yes_btn_title,no_btn_title],[yes_callback,no_callback])]]
    'createContents'  : function(type, options){
        var h = '', self  = this;
        if(!options){
            //如果只有一个参数,则认为其传递的是HTML字符串,直接返回显示
            return type;
        }
        //区分不同类型进行处理
        switch(type){

            case 'html':
                //从服务器获取直接获取内容进行直接显示,不做任何封装(一般用于获取表单)
                $.get(options.url, options.data, function(data){
                    if(typeof(data) == 'object' && data.error !== '0'){
                        self.setContents('message',{'type':'error','text':data.msg,'time':options.etime,'callback':options.ecallback,'data':data});
                    }else if(options.target){
                        //有target参数插入当前文档(先清空其中内容)
                        $('#'+options.target).empty().append(data);
                        self.hide();
                    }else{
                        data = $(data);//必须要包装一下,下面这个函数里的$第二参数必须是对象
                        Seller.setSubmit(data);//重新绑定全局默认提交事件
                        self.setContents(data);
                    }
                }).error(function(){
                    self.setContents('message',{'type':'info','text':Seller.LANG.MSG_REMOTE_ERROR,'time':options.itime,'callback':options.icallback});
                }).complete(function(){
                    self.setPosition(self.lastPos);
                });

                //异步开始,先提示正在加载
                h = this.createContents('loading', 'loading...');
                break;

            case 'post':
                //ajax-post提交反馈对话框结果
                $.post(options.url, options.data, function(data){
                    if(data.error !== '0'){
                        self.setContents('message',{'type':'error','text':data.msg,'time':options.etime,'callback':options.ecallback,'data':data});//失败
                    }else{
                        self.setContents('message',{'type':'success','text':data.msg,'time':options.stime,'callback':options.scallback,'data':data});//成功
                    }
                }).error(function(){
                    self.setContents('message',{'type':'info','text':Seller.LANG.MSG_REMOTE_ERROR,'time':options.itime,'callback':options.icallback});
                }).complete(function(){
                    self.setPosition(self.lastPos);
                });

                h = this.createContents('loading',  '处理提交中...');
                break;

            case 'json':
                //ajax-json从服务器获取json数据,供回调函数执行
                $.getJSON(options.url, options.data, function(data){
                    if(data.error !== '0'){
                        self.setContents('message',{'type':'error','text':data.msg,'time':options.etime,'callback':options.ecallback,'data':data});//失败
                    }else{
                        if(options.scallback){
                            if(!options.stime){
                                options.stime = 0;
                            }
                            setTimeout(function(){options.scallback.call(this, data)}, options.stime);
                        }
                        self.hide();
                    }
                }).error(function(){
                    self.setContents('message',{'type':'info','text':Seller.LANG.MSG_REMOTE_ERROR,'time':options.itime,'callback':options.icallback});
                }).complete(function(){
                    self.setPosition(self.lastPos);
                });

                h = this.createContents('loading',  '处理提交中...');
                break;

            //以下是内置的几种常用对话框类型
            case 'loading':
                //todo:显示的文字和动图的相对未知有待调整
                h = '<div class="dialog_loading"><div class="dialog_loading_text">' + options + '</div></div>';
                break;

            //信息提示子类型,从参数中重新获取类型(右上角关闭按钮的默认事件均被回调参数插入)
            case 'message':

                //提示信息宽度固定402
                self.setWidth(402);

                type = options.type;

                var time                = options.time;
                var message_body        = $('<div class="dialog_message_body"></div>');
                var message_contents    = $('<div class="dialog_message_contents dialog_message_' + (type?type:'error') + '"><i class="alert_'+ (type?type:'error') +'"></i>' + options.text + '</div>');
                var buttons_bar         = $('<div class="dialog_buttons_bar"></div>');
                switch(type){

                    //信息提示
                    case 'info':
                    case 'error':
                        //默认10秒执行(整个对话框设置的唯一的默认值参数)
                         time = time?time:10;
                    case 'success':
                        //success有可能是直接跳转的,不能设置默认值

                        if(time){
                            //自动倒计时关闭,到时间后执行指定函数
                            $('<time class="countdown"><i class="icon-time"></i><span id="countdown_second">'+ time +'</span> 秒后关闭窗口</time>').appendTo(buttons_bar);
                            //直接关闭对话框可立即执行(解绑右上角默认关闭)
                            $(this.dom.close_button).unbind('click').click(function(){
                                time = -1;//点击执行后取消倒计时执行
                                if(options.callback){
                                    if(!options.callback.call(this, options.data)){
                                        return;
                                    }
                                }
                                self.hide();
                            });
                            (function(){
                                $('#countdown_second').text(time<0?0:time);//手动点击小于0,重置为0
                                if(time > 0){
                                    time--;
                                    setTimeout(arguments.callee,1000);
                                }else if(time == 0){
                                    if(options.callback){
                                        if(!options.callback.call(this, options.data)){
                                            return;
                                        }
                                    }
                                    self.hide();
                                }//执行关闭点击被设置成-1走这里
                            }());
                        }else{
                            //没有定义时间则不给提示信息,直接执行回调
                            if(options.callback){
                                if(!options.callback.call(this, options.data)){
                                    //上面的return返回的是自执行匿名函数,这里的return返回的当前createContents方法,所以要返回当前默认body值
                                    return $(self.dom.content).html();
                                }
                            }
                            self.hide();
                        }
                        break;

                    //确认对话框
                    case 'confirm':
                        //两个都是重新创建的,解绑操作其实没有必要
                        $('<a href="javascript:;" class="dialog-bottom-btn mr10">'+ (options.yes_btn_title?options.yes_btn_title:'确定') +'</a>').unbind('click').click(function(){
                            if(options.yes_callback){
                                if(!options.yes_callback.call()){
                                    return;
                                }
                            }
                            self.hide();
                        }).appendTo(buttons_bar);
                        $('<a href="javascript:;" class="dialog-bottom-btn">'+ (options.no_btn_title?options.no_btn_title:'取消') +'</a>').unbind('click').click(function(){
                            if(options.no_callback){
                                if(!options.no_callback.call()){
                                    return;
                                }
                            }
                            self.hide();
                        }).appendTo(buttons_bar);

                        //右上角关闭按钮绑定取消按钮的回调
                        $(this.dom.close_button).unbind('click').click(function(){
                            if(options.no_callback){
                                if(!options.no_callback.call()){
                                    return;
                                }
                            }
                            self.hide();
                        });
                        break;

                    //只做开发者提示,不会出现在前端
                    default : message_contents = message_contents.html('<i class="alert_info"></i>'+Seller.LANG.MSG_FATAL_ERROR+(Seller.APP_DEBUG?'(对话框子类型不存在)':''));//todo:倒计时不方便实现
                }
                h = $(message_body).append(message_contents).append(buttons_bar);
                //提示信息取消边框阴影
                this.setStyle({'box-shadow':'0 0'});
                break;

            //只做开发者提示,不会出现在前端
            default : h = this.createContents('message',{'type':'info','text':Seller.LANG.MSG_FATAL_ERROR+(Seller.APP_DEBUG?'(对话框类型不存在)':'')});

        }
        return h;
    }
};



//对话框应用层封装
//闭包实现对话框实例池
Seller.getDialog        = (function(){
    var dialogs = {};
    return function(id){
        if(!dialogs[id]){
            dialogs[id] = new Seller.Dialog(id);
        }
        return dialogs[id];
    };
}());
//确认对话框
Seller.confirmDialog    = function(text, callback){
    var options = {
        'type'          : 'confirm',
        'text'          : text,
        'yes_callback'  : callback,
        'no_coallback'  : undefined
    };
    return Seller.getDialog('confirm_dialog_id').setWidth(402).setTitle('提示信息').setContents('message',options).show('center');
};
//ajax获取html生成对话框
Seller.htmlDialog       = function(title, width, url, data, target){
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
    if(url){
        return Seller.getDialog('html_dialog_id').setWidth(width).setTitle(title).setContents('html',options).show('center');
    }else{
        return Seller.getDialog('html_dialog_id').setWidth(width).setTitle(title).setContents(data).show('center');
    }
};
//ajax-post返回结果对话框
Seller.postDialog       = function(url, data, jump, hint){
    var options = {
        'url'       : url,
        'data'      : data,
        'itime'     : undefined,
        'stime'     : hint?0:2,
        'etime'     : 3,
        'icallback' : undefined,
        'scallback' : function(data){
            if(jump){
                location.href = Seller.addUrlVar(jump, data.data);
            }else if(data.url){
                location.href = data.url;
            }else{//todo:全局查找location中带锚链接的bug
                location.reload(true);
            }
            //return true;//直接跳转,不再显示
        },
        'ecallback' : undefined
    };
    return Seller.getDialog('post_dialog_id').setWidth(402).setTitle('提示信息').setContents('post',options).show('center');
};
//ajax-json返回json结果供回调函数执行
Seller.jsonDialog       = function(url, data, callback){
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
    return Seller.getDialog('json_dialog_id').setWidth(402).setTitle('提示信息').setContents('json', options).show('center');
};
//一般信息提示框
Seller.infoDialog       = function(text, time, callback){
    var options = {
        'type'      : 'info',
        'text'      : text,
        'time'      : time,
        'callback'  : callback
    };
    return Seller.getDialog('info_dialog_id').setWidth(402).setTitle('提示信息').setContents('message',options).show('center');
};





//jQuery动态凸显触及图片样式扩展
(function($) {
    $.fn.jfade = function(settings) {
        //基本配置
        var defaults = {start_opacity: "1",	high_opacity: "1",low_opacity: ".1",timing: "500"};
        settings = $.extend(defaults, settings);
        settings.element = $(this);
        $(settings.element).css("opacity", settings.start_opacity);

        //hover事件切换
        $(settings.element).hover(
            //鼠标进
            function(){
                $(this).stop().animate({opacity: settings.high_opacity},settings.timing); //100% opacity for hovered object
                $(this).siblings().stop().animate({	opacity: settings.low_opacity},settings.timing); //dimmed opacity for other objects
            },
            //鼠标出
            function(){
                $(this).stop().animate({opacity: settings.start_opacity	},settings.timing); //return hovered object to start opacity
                $(this).siblings().stop().animate({	opacity: settings.start_opacity},settings.timing); // return other objects to start opacity
            }
        );
        return this;
    }
})(jQuery);



//onload事件最开始执行
$(function(){

    if($.cookie('quick_menu')){
        $(".sitemap-menu-arrow").show();
        $(".sitemap-menu").show();
    }
    //导航管理打开
	$('.index-sitemap > a').click(function(){
        $(".sitemap-menu-arrow").slideToggle("slow");
        $(".sitemap-menu").slideToggle("slow");
        $.cookie('quick_menu', 1, {'path':'/'});
    });
    //关闭导航管理
	$('#closeSitemap').click(function() {
        $(".sitemap-menu-arrow").slideUp("fast");
        $(".sitemap-menu").slideUp("fast");
        $.cookie('quick_menu', null, {'path':'/'});
    });
    //顶部大菜单鼠标掠过效果
    $(".ncsc-nav dl").hover(
        function(){$(this).addClass("hover");},
        function(){$(this).removeClass("hover");}
    );
    //浮动导航  waypoints.js
    $("#sidebar,#mainContent").waypoint(function(event, direction) {
        $(this).parent().toggleClass('sticky', direction === "down");
        event.stopPropagation();
    });

    //表单提交绑定默认事件
    Seller.setSubmit();

});










//todo:等待整理

//返回到顶部
$(function() {
    backTop=function (btnId){
	var btn=document.getElementById(btnId);
	var scrollTop = document.documentElement.scrollTop || document.body.scrollTop;
	window.onscroll=set;
	btn.onclick=function (){
		btn.style.display="none";
		window.onscroll=null;
		this.timer=setInterval(function(){
		    scrollTop = document.documentElement.scrollTop || document.body.scrollTop;
			scrollTop-=Math.ceil(scrollTop*0.1);
			if(scrollTop==0) clearInterval(btn.timer,window.onscroll=set);
			if (document.documentElement.scrollTop > 0) document.documentElement.scrollTop=scrollTop;
			if (document.body.scrollTop > 0) document.body.scrollTop=scrollTop;
		},10);
	};
	function set(){
	    scrollTop = document.documentElement.scrollTop || document.body.scrollTop;
	    btn.style.display=scrollTop?'block':"none";
	}
	};
	backTop('gotop');

});



