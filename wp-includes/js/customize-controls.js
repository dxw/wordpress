(function(a,c){var b=wp.customize;b.Previewer=b.Messenger.extend({refreshBuffer:250,initialize:function(e,d){c.extend(this,d||{});this.loaderUuid=0;this.refresh=(function(f){var g=f.refresh,i=function(){h=null;g.call(f)},h;return function(){if(typeof h!=="number"){if(f.loading){f.loading.remove();delete f.loading;f.loader()}else{return i()}}clearTimeout(h);h=setTimeout(i,f.refreshBuffer)}})(this);this.iframe=b.ensure(e.iframe);this.form=b.ensure(e.form);this.container=this.iframe.parent();b.Messenger.prototype.initialize.call(this,e.url,{targetWindow:this.iframe[0].contentWindow});this._formOriginalProps={target:this.form.prop("target"),action:this.form.prop("action")};this.bind("url",function(f){if(this.url()==f||0!==f.indexOf(this.origin()+"/")||-1!==f.indexOf("wp-admin")){return}this.url(f);this.refresh()});this.refresh();this.form.on("keydown",function(f){if(13===f.which){f.preventDefault()}})},loader:function(){var d=this,e;if(this.loading){return this.loading}e=this.iframe.prop("name");this.loading=c("<iframe />",{name:e+"-loading-"+this.loaderUuid++}).appendTo(this.container);this.loading.one("load",function(){d.iframe.remove();d.iframe=d.loading;delete d.loading;d.iframe.prop("name",e)});return this.loading},refresh:function(){this.submit({target:this.loader().prop("name"),action:this.url()})},submit:function(d){if(d){this.form.prop(d)}this.form.submit();if(d){this.form.prop(this._formOriginalProps)}}});c(function(){if(!b.settings){return}var e,d=c('[name^="'+b.settings.prefix+'"]');e=new b.Previewer({iframe:"#customize-preview iframe",form:"#customize-controls",url:b.settings.preview});c.each(b.settings.values,function(i,h){var g=d.filter('[name="'+b.settings.prefix+i+'"]'),f=b.set(i,h);f.control=new wp.customize.Element(g);f.control.link(f);f.link(f.control);f.bind(e.refresh)});c(".control-section h3").click(function(){c(this).siblings("ul").slideToggle(200);c(this).toggleClass("open");return false});c("#save").click(function(){e.submit();return false});c('[name^="'+b.settings.prefix+'"]').each(function(){})})})(wp,jQuery);