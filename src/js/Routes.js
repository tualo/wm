Ext.define('TualoOffice.routes.WM',{
    url: 'wm',
    handler: {
        action: function(token){
            console.log('onAnyRoute',token);
            alert('wm','ok');
        },
        before: function (action) {
            console.log('onBeforeToken',action);
            console.log(new Date());
            action.resume();
        }
    }
});