var Jiniu1 = {
    setTime:function(minTime,maxTime,obj,show){
        //最小时间限制，最大时间限制，事件对象，被插入内容对象
        var calendar = new datePicker();
        calendar.init({
            'trigger': obj, /*按钮选择器，用于触发弹出插件*/
            'type': 'datetime',/*模式：date日期；datetime日期时间；time时间；ym年月；*/
            'minDate':minTime,/*最小日期*/
            'maxDate':maxTime,/*最大日期*/
            'onSubmit':function(){/*确认时触发事件*/
                var theSelectData = calendar.value;
                $(obj).text(theSelectData);
                $(show).val(theSelectData);
                $(show).text(theSelectData);
            },
            'onClose':function(){}
        });
    },music:function(obj){
        $(obj).click(function(){
            var myAudio = document.getElementById('audio1');
            if(myAudio.paused){
                myAudio.play();
                $(obj).addClass('musicP');
            }else{
                myAudio.pause();
                $(obj).removeClass('musicP');
            }
        }).click();
    },close:function(obj,show,str,liClass,maxlength,callback){
         //事件对象，插入容器，插入内容，同一个容器内克隆多种对象区分li标签 最大克隆次数，回调事件

        $(obj).click(function(){
            $(show).append(str);
            if($(show).find(liClass).length == maxlength) $(this).addClass('none');
            var close = $(show).find('i.close');
            if(close){
                close.bind('click',function(){
                    console.log(close);
                    $(obj).removeClass('none');
                    $(this).parent(liClass).remove();
                });
            };
            callback();
        });
    },tabClass:function(clickObj,changeObj,tab,tac,callback,callback1){
         //事件对象，改变样式对象，状一样式名，状态二样式名，状态一回调事件，状态而回调事件
        $(clickObj).click(function(){
            var set = $(changeObj);
            if(set.hasClass(tab)){
                $(this).text('预览');
                set.removeClass(tab).addClass(tac);
                callback();
            }else {
                $(this).text('返回编辑');
                set.removeClass('tac').addClass('tab');
                callback1();
            }
        });
    },textChange:function(text,show){  //输入框，显示容器
        $(text).bind('change',function(){
            var text =  $(this).val().split('\n').join('<br />');
            $(this).next(show).html(text);
        });
    }
};
/*var WxJiniu = {
    srollbutton:function(){

    },
    srollbutton.prototype = {

    },
    loading:function(){
        $(body).append('<div class="loading">加载中...</div>');
    }, close_loading:function(){
        $('.loading').remove();
    }, sroll_loading:function(){
        alert('1');
    },close_loading1:function(){
        alert('2');
    },srollButton = function(){

    }
}*/
/*function srollButton(met){
    this.p = met.p;
    this.url = met.url;
    this.obj = met.obj;
    this.srollObj = met.srollObj;
};*/
/*
srollButton.loading = function(){
    $(body).append('<div class="loading">加载中...</div>');
};

srollButton.close = function(){
    $(body).append('<div class="loading">加载中...</div>');
}
srollButton.ajax =function(){
    if(this.p == 1) this.loading();
    else this.loading_();
    Jiniu.ajax(this.url+this.p,'',this.run,'POST','JSON',false);
}
srollButton.sroll = function(obj){
    if (obj == window){
        //解决html5和W3C文档的兼容性
        var scrollTop = window.pageYOffset  //用于FF
            || document.documentElement.scrollTop
            || document.body.scrollTop;
        var clientHeight = document.documentElement.clientHeight
            || document.body.clientHeight;
        var scrollHeight = document.documentElement.scrollHeight
            || document.body.scrollHeight;
        if (((scrollTop + clientHeight) / scrollHeight) >= 1) {
            this.ajax();
        };
    } else {
        if (obj.offsetHeight + obj.scrollTop >= obj.scrollHeight) this.ajax();
    }
};
srollButton.prototype.run = function(ex){
    if(this.p==0){
        this.
    }
};
*/
