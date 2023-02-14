/**
 * Folio community admin script
 */

( function ( $, pluginData ) {


  /**
   * Initialize application
   */
  $( function () {

    console.log( '🚀 Folio community settings ready!' );

    function formatSite (site) {
        if (site.loading) {
            return site.text;
        }
    
        var $container = $(
            "<div class='select2-result-site clearfix'>" +
                "<div class='select2-result-site__title'></div>" +
                "<div class='select2-result-site__domain'></div>" +
            "</div>"
        );
    
        $container.find(".select2-result-site__title").text(site.title);
        $container.find(".select2-result-site__domain").text(site.text);
    
        return $container;
    }
    
    function formatSiteSelection (site) {
        return site.text;
    }

    
    let settings = {
        allowClear: true,
        width: '280px',
        minimumInputLength: 3,
        language: pluginData.lang ?? 'en',
        templateResult: formatSite,
        templateSelection: formatSiteSelection,
        ajax: {
            url: pluginData.ajax_url ?? '',
            dataType: 'json',
            data: function (params) {
                var query = {
                    'ajax_nonce': pluginData.ajax_nonce ?? '',
                    'action': 'folio_comm_search_site_ajax',
                    'search': params.term,
                }
                return query;
            },
            processResults: function (response) {
                return {
                    results: response
                };
            },
            cache: true
        }
    };

    $('.folio-community-site-select2').select2( $.extend({}, settings, { 'placeholder': pluginData.select_one }) );
    $('.folio-community-index-select2').select2( $.extend({}, settings, { 'placeholder': pluginData.select_all }) );
    
  } )

} )( jQuery, typeof folioCommunityAdmin !== 'undefined' ? folioCommunityAdmin : {})