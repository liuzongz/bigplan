/**
 * 对话框构造函数
 */
Dialog                  = function(id, lang, init){

    /**
     * 对话框内存标识符(this.id保存好像没什么用)
     */
    this.id     = id;

    /**
     * 外部传参错误提示信息
     */
    this.lang  = lang;

    /**
     * 远程html获取后绑定全局初始化事件
     */
    this.init   = init;

    /**
     * 对话框遮蔽层
     * 外部仅使用[lock,unlock]两个方法
     */
    this.locker             = {
        'style'     : {'position':'absolute','top':'0px','left':'0px','backgroundColor':'#000','opacity':0.1},
        'masker'    : null,
        'lock'      : function(content){
            //全局只生成一个遮蔽层
            this.masker = $('#dialog_manage_screen_locker');
            if(this.masker.length){
                this.zIndex         = parseInt(this.masker.css('zIndex')) + 2;//再次锁定时跳过上层内容
                //隐藏上一层内容
                $("div[id^='fwin_'][style*='z-index: "+ (this.zIndex - 1) +"']").hide();
            }else{
                this.zIndex         = 999;//动态读写的层级
                this.style.zIndex   = this.zIndex;//this.style.zIndex专门用于记录初始值,用于最后比对删除
                //生成遮蔽层
                this.masker         = $('<div id="dialog_manage_screen_locker"></div>').css(this.style);
                //占整屏
                this.masker.width($(document).width()).height($(document).height());
                //插入文档
                $(document.body).append(this.masker);
                //this.masker.show();
            }
            //设置遮蔽层层级
            this.masker.css('zIndex', this.zIndex);
            //设置主内容层级(永远比当前内容层多1)
            content.css('zIndex', this.zIndex + 1);
        },
        'unlock'    : function(){
            if(null !== this.masker){
                if(this.style.zIndex == this.zIndex){
                    //回到最初记录的默认值,进行删除操作
                    this.masker.remove();
                    this.masker = null;
                }else{
                    this.zIndex = this.zIndex - 2;
                    this.masker.css('zIndex', this.zIndex);
                    //还原下层对话框
                    $("div[id^='fwin_'][style*='z-index: "+ (this.zIndex + 1) +"']").show();
                }
            }
        }
    };

    /**
     * 对话框核心结构
     */
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

    /**
     * 对话框基本默认事件
     */
    var self = this;//不再同一个作用域中
    $(this.dom.close_button).click(function(){
        //对话框默认关闭事件,可覆盖
        self.hide();
    });
    $(this.dom.wrapper).draggable({
        //对话框头部可拖动
        'handle' : this.dom.head
    });

    /**
     * 插入文档流
     */
    $(document.body).append(this.dom.wrapper);
};

/**
 * 对话框原型
 */
Dialog.prototype        = {

    /**
     * 在构造函数中初始化
     */
    //外部传参
    'id'            : null,
    'lang'          : null,
    'init'          : null,
    //html结构初始化
    'locker'        : null,
    'dom'           : null,
    //内部使用
    'lastPos'       : null,

    /**
     * 显示对话框(并没有删除对话框,用zIndex属性标记删除)
     */
    'show'          : function(pos){
        if(pos) this.setPosition(pos);
        //显示状态的正常对话框不在显示,以免造成多层次遮盖层结构异常
        if($(this.dom.wrapper).css('zIndex') > 0) return;

        //模态
        this.locker.lock($(this.dom.wrapper));
        $(this.dom.wrapper).show();
        return this;
    },

    /**
     * 隐藏对话框
     */
    'hide'          : function(){
        //清除各种状态
        var self = this;
        $(this.dom.wrapper).css({'zIndex':0}).hide();
        $(this.dom.title).html(this.lang.MSG_FATAL_ERROR);
        $(this.dom.content).html('');
        $(this.dom.close_button).unbind('click').click(function(){self.hide();});
        this.locker.unlock();
        return this;
    },

    /**
     * 设置对话框位置
     * lastPos使用
     */
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

    /**
     * 设置标题
     */
    'setTitle'      : function(title){
        $(this.dom.title).html(title);
        return this;
    },

    /**
     * 设置对话框样式
     * 仅针对最外包裹层
     */
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

    /**
     * 宽高设置
     * @param width
     * @returns {Dialog}
     */
    'setWidth'      : function(width){
        this.setStyle({'width' : width + 'px'});
        return this;
    },
    'setHeight'     : function(height){
        this.setStyle({'height' : height + 'px'});
        return this;
    },

    /**
     * 内容设置
     * 根据核心方法[createContents]不同类型的对话框
     */
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

    /**
     * 多种方式生成对话框内容
     * 没有options,直接显示type
     * type[html],options[url,data,itime,icallback,etime,ecallback,target]//成功直接插入
     * type[post],options[url,data,itime,icallback,stime,scallback,etime,ecallback]//根据返回值生成提示对话框error,success
     * type[json],options[url,data,itime,icallback,stime,scallback,etime,ecallback]//参数同post,只是在成功时是直接执行,没有对话框显示
     * type[loading],options显示字符
     * type[message],options[text,type[success/error/info(time,callback,data<可以从服务器获取数据传参>),confirm([yes_btn_title,no_btn_title],[yes_callback,no_callback])]]
     */
    'createContents'  : function(type, options){
        var h = '', self  = this;
        if(undefined === options){
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
                        self.init.call(this, data);//重新绑定全局默认提交事件
                        self.setContents(data);
                    }
                }).error(function(){
                    self.setContents('message',{'type':'info','text':self.lang.MSG_REMOTE_ERROR,'time':options.itime,'callback':options.icallback});
                }).complete(function(){
                    self.setPosition(self.lastPos);
                });

                //异步开始,先提示正在加载
                h = this.createContents('loading', '');
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
                    self.setContents('message',{'type':'info','text':self.lang.MSG_REMOTE_ERROR,'time':options.itime,'callback':options.icallback});
                }).complete(function(){
                    self.setPosition(self.lastPos);
                });

                h = this.createContents('loading',  '');
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
                    self.setContents('message',{'type':'info','text':self.lang.MSG_REMOTE_ERROR,'time':options.itime,'callback':options.icallback});
                }).complete(function(){
                    self.setPosition(self.lastPos);
                });

                h = this.createContents('loading',  '');
                break;

            //以下是内置的几种常用对话框类型
            case 'loading':
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

                                    //一般情况下,成功对话框都会直接刷新页面,是不会经过这里的
                                    //新建相册成功除外,这里清空html对话框
                                    if('success' === type){
                                        Dialog.get('_').hide();
                                    }

                                }//执行关闭点击被设置成-1走这里,上面全部略过
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
                        $('<a href="javascript:;" class="dialog-bottom-btn" style="margin-right:10px;">'+ (options.yes_btn_title?options.yes_btn_title:'确定') +'</a>').unbind('click').click(function(){
                            if(options.yes_callback){
                                if(!options.yes_callback.call()){
                                    return;
                                }
                            }
                            if(self.dom.wrapper.is(":visible")){
                                //如果confirm框已经隐藏,说明有其他对话触发了hide,那么就不能在hide,否则会破坏多级对话框的层级关系
                                self.hide();
                            }
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

                    //只做开发者提示,不会出现在前端//todo:倒计时不方便实现
                    default : message_contents = message_contents.html('<i class="alert_info"></i>'+self.lang.MSG_FATAL_ERROR);
                }
                h = $(message_body).append(message_contents).append(buttons_bar);
                //提示信息取消边框阴影
                this.setStyle({'box-shadow':'0 0'});
                break;

            //只做开发者提示,不会出现在前端
            default : h = this.createContents('message',{'type':'info','text':self.lang.MSG_FATAL_ERROR});

        }
        return h;
    }
};

/**
 * 闭包实现对话框实例池
 */
Dialog.get              = (function(){
    var dialogs = {};
    return function(id, lang, init){
        if(!dialogs[id]){
            dialogs[id] = new Dialog(id, lang, init);
        }
        return dialogs[id];
    };
}());


