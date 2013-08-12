/**
 * Author: Denisov Denis
 * Email: denisovdenis@me.com
 * Date: 12.08.13
 * Time: 13:25
 */

(function ($, mw) {

    var PageGuests = {
        namespace: 'PageGuests',
        title: mw.message('pageguests-tab-title').text(),

        addTab: function () {
            console.log('wgCanonicalNamespace: ' + wgCanonicalNamespace);
            console.log('wgTitle: ' + wgTitle);
            console.log('this.namespace: ' + this.namespace);
            if (wgCanonicalNamespace != this.namespace && wgTitle != this.title) {
                console.log('addTabe ok');
                addPortletLink('p-namespaces',
                    wgArticlePath.replace('$1', PageGuests.namespace + ':' + encodeURIComponent(wgPageName)),
                    PageGuests.title);
            }
        }
    };

    $(function ($) {
        console.log('FUCKYOU');
        PageGuests.addTab();
    });

})(jQuery, mediaWiki);
