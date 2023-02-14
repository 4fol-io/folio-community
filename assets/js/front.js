/**
 * Folio community frontend script
 */

import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import 'dayjs/locale/ca';
import 'dayjs/locale/es';

( function ( $, pluginData ) {


    if ( pluginData.hasOwnProperty('lang') && 
        ( pluginData.lang === 'es' || pluginData.lang === 'ca') ) {
        dayjs.locale(pluginData.lang);
    }

    const inSpeed = 350;
    const outSpeed = 250;
    let updateTimeout = null;
    let postTimes = [];
    let refreshTime = dayjs();
    const refreshTimeDisplay = $('.folio-comm-refresh-time');

    // Show an element
    var showContent = function (elem) {

        var getHeight = function () {
            elem.style.display = 'block';
            var height = elem.scrollHeight + 'px';
            elem.style.display = '';
            return height;
        };

        var height = getHeight();
        elem.classList.add('is-visible');
        elem.style.height = height;

        setTimeout(function () {
            elem.style.height = '';
        }, inSpeed);

    };

    // Hide an element
    var hideContent = function (elem) {
        elem.style.height = elem.scrollHeight + 'px';
        setTimeout(function () {
            elem.style.height = '0';
        }, 1);
        setTimeout(function () {
            elem.classList.remove('is-visible');
        }, outSpeed);
    };

    // Toggle element visibility
    var toggleContent = function (elem, timing) {
        if (elem.classList.contains('is-visible')) {
            hide(elem);
            return;
        }
        show(elem);
    };
    

    class LoadMore {

        constructor(type) {

            this.type = type ?? 'pubs';

            this.totalPages = 0;
            this.paginationBase = '%_%';
            this.ajaxUrl = pluginData.ajax_url ?? '';
            this.ajaxNonce = pluginData.ajax_nonce ?? '';
            this.ajaxAction = 'folio_comm_load_more_'  + this.type;
            this.refreshButton = $('#folio-comm-refresh-'+ this.type +'-btn');
            this.moreButton = $('#folio-comm-load-more-' + this.type + '-btn');
            this.contentWrapper = $('.folio-comm-search-results-wrapper');
            this.fieldWrapper = $('.folio-comm-search-field-wrapper');
            this.moreContent = $( '#folio-comm-load-more-'+ this.type +'-content' );
            this.morePaging = $( '#folio-comm-load-more-'+ this.type +'-paging' );
            this.isRefresh = false;

            this.searchFilters = [];
            this.searchForm = $('.folio-comm-search-form');

            this.pagination = this.moreButton.length ? 
                this.moreButton.hasClass('folio-comm-more-btn') ? 
                'more' : 'infinite' : 'pages';
            
            this.options = {
                root: null,
                rootMargin: '50px 0px',
                threshold: 0,
            };

            this.init();

        }

        /**
         * Initialize instance 
         */
        init () {

            if ( this.moreContent.length ) {
                this.paginationBase = this.moreContent.data( 'base' );
            }

            if(this.type === 'search'){
                const field = this.searchForm.find('input[name="q"]');
                const v = field.val();
                if ( v ) {
                    this.showSearchLoading();
                    this.hideSearchLoading();
                }
            }

            if ( this.morePaging.length ) {
                this.totalPages = this.morePaging.data( 'total-pages' );
                if (this.totalPages > 1) {
                    this.moreButton.show();
                }
            }

            if(this.pagination === 'infinite'){
                let observer = new IntersectionObserver( ( entries ) => this.intersectionObserverCallback( entries ), this.options );
                observer.observe( this.moreButton[ 0 ] );
            }

            this.setupEvents();

        }


        /**
         * Setup events
         */
        setupEvents (){

            if ( this.refreshButton.length ) {
                this.refreshButton.on('click', (e) => {
                    e.preventDefault();
                    refreshTime = dayjs();
                    postTimes = [];
                    this.refresh();
                });
            }

            if ( this.pagination === 'more' ) {
                this.moreButton.on('click', () => {
                    this.handleLoadMore();
                });
            }

            if ( this.type === 'search' ) {

                this.filter = $('.folio-comm-search-filters :checkbox[name="f"]');
                this.searchFilters = this.getSearchFilters();

                this.filter.on('change', (e) => {
                    this.searchFilters = this.getSearchFilters();
                    this.checkSearchAction();
                });

                this.searchForm.on( 'submit', (e) => {
                    const field = this.searchForm.find('input[name="q"]');
                    const v = field.val();
                    if ( !v ) {
                        e.preventDefault();
                        field.trigger('focus');
                        return false;
                    }
                    if(this.searchFilters.length){
                        e.preventDefault();
                        this.refresh();
                        return false;
                    }
                    return true;
                } );

            }

        }


        /**
         * Get search filters
         * 
         * @returns array
         */
        getSearchFilters(){
            return $( '.folio-comm-search-filters :checkbox[name="f"]:checked' ).map((i, el) => el.value).get();
        }


        /**
         * Check search type (local, external)
         */
        checkSearchAction(){
            if(this.searchFilters.length){
                this.searchForm.attr('target','_self').attr('action',this.searchForm.data('local-action'));
            }else{
                this.searchForm.attr('target','_blank').attr('action',this.searchForm.data('external-action'));
            }
        }


        /**
         * Intersection observer callback
         *
         * @param {array} entries elements under observation.
         *
         * @return null
         */
        intersectionObserverCallback ( entries ) {

            entries.forEach( entry => {
                if ( entry?.isIntersecting ) {
                    if (!this.isRefresh) this.handleLoadMore();
                }
            } );

        }


        /**
         * Load more handle
         *
         * 1. Make an ajax request, by incrementing the page num by one on each request (in is not a refresh).
         * 2. Append new/more posts to the existing content.
         * 3. If the response is 0 ( which means no more posts available ), remove the load-more button from DOM.
         *
         * @return null
         */
        handleLoadMore () {

            const page = this.moreContent.data( 'page' );
            const per_page = this.moreContent.data( 'per-page' );
            
            if ( !page ) {
                return null;
            }

            let nextPage = parseInt( page ) + 1;
            if(this.isRefresh){
                nextPage = 1;
            }

            if (this.pagination === 'more'){
                this.moreButton.children('.folio-comm-label').addClass('folio-comm-hidden');
                this.moreButton.children('.folio-comm-loading').removeClass('folio-comm-hidden');
                this.moreButton.addClass('loading');
            }

            var formData = new FormData();

            formData.append('page', nextPage);
            formData.append('per_page', per_page);
            formData.append('is_refresh', this.isRefresh);
            formData.append('pagination', this.pagination);
            formData.append('base', this.paginationBase + location.search.replace('?','&'));
            formData.append('action', this.ajaxAction);
            formData.append('ajax_nonce', this.ajaxNonce);

            if (this.type === 'search'){
                this.searchFilters = this.getSearchFilters();
                formData.append('filters', this.searchFilters);
                formData.append('search', this.searchForm.find('input[name="q"]').val());
            }

            $.ajax( {
                url: this.ajaxUrl,
                type: 'post',
                data: formData,
                processData: false,
                contentType: false,
                success: ( response ) => {
                    if(response !== '0'){
                        let last = null;

                        if ( this.type === 'search' && this.pagination === 'pages' ) {
                            this.moreContent.html( '' )
                        }

                        if(this.pagination === 'more'){
                            last = this.moreContent.children().last();
                        }

                        this.moreContent.append( response );

                        if(this.pagination === 'more' && last && last.length){
                                const next = last.next();
                                if(next.length){
                                    next.trigger('focus');
                                }
                        }
                    }
                },
                error: ( response ) => {
                    console.log( response );
                },
                complete: ()  => {

                    this.morePaging = $( '#folio-comm-load-more-'+ this.type +'-paging' );
                    this.totalPages = this.morePaging.length ? this.morePaging.data( 'total-pages' ) : 0;

                    this.hideSearchLoading();
        
                    if(this.pagination !== 'pages'){
                        this.moreContent.data( 'page', nextPage ).attr( 'data-page', nextPage );
                    }
                    
                    if (this.pagination === 'more'){
                        this.moreButton.children('.folio-comm-label').removeClass('folio-comm-hidden');
                        this.moreButton.children('.folio-comm-loading').addClass('folio-comm-hidden');
                        this.moreButton.removeClass('loading');
                    }

                    this.isRefresh = false;
                    this.hideLoadMoreIfOnLastPage( nextPage );

                    if (this.type === 'pubs'){
                        resetClock();
                    }
                }
            } );
        }

        showSearchLoading = () => {
            
            if ( this.type === 'search' && this.pagination === 'pages' ) {
                console.log('show', this.type);
                if (this.contentWrapper.length){
                    if (this.fieldWrapper.length){
                        this.fieldWrapper.find('.folio-comm-loading').removeClass('folio-comm-hidden');
                    }
                    hideContent(this.contentWrapper[0]);
                }
            }
        }

        hideSearchLoading = () => {
        
            if ( this.type === 'search' && this.pagination === 'pages' ) {
                if (this.contentWrapper.length){
                    console.log('hide', this.type);
                    setTimeout(() => { 
                        if (this.fieldWrapper.length){
                            this.fieldWrapper.find('.folio-comm-loading').addClass('folio-comm-hidden');
                        }
                        showContent(this.contentWrapper[0]); 
                    }, 350);
                }
            }
        }

        /**
         * Refresh
         */
        refresh = () => {
            this.isRefresh = true;
            this.moreContent.data('page', 1).attr('data-page', 1);
            if ( this.type === 'search' && this.pagination === 'pages' ) {
                this.moreContent.show();
            }else{
                this.moreContent.html( '' ).show();
            }

            if(this.pagination !== 'pages'){
                this.moreButton.show();
            }else{
                let param = this.type === 'pubs' ? 'pp' : 'pq';
                this.updateUrlParam(param);
            }
            if ( this.type === 'search' ) {
                this.updateUrlParam('f', this.searchFilters);
                this.updateUrlParam('q', this.searchForm.find('input[name="q"]').val());
            }

            this.showSearchLoading();
            this.handleLoadMore();
        }

        
        /**
         * Hide Load more Button If on last page.
         *
         * @param {int} nextPage New Page.
         */
        hideLoadMoreIfOnLastPage = ( nextPage ) => {
            if ( nextPage + 1 > this.totalPages ) {
                this.moreButton.hide();
            }
        }


        /**
         * update url parameter 
         */
        updateUrlParam = ( param, value ) => {
            if (typeof URLSearchParams !== 'undefined') {
                const params = new URLSearchParams(location.search);
                if(params.has(param)){
                    params.delete(param);
                }
                if(typeof value !== 'undefined'){
                    params.append(param, value);
                }
                if(window.history && history.replaceState){
                    history.replaceState(null, '', '?' + params + location.hash);
                }
            }
        }

    }

    /**
     * Setup clock for human friendly update time
     */
    const setupClock = function() {  
        dayjs.extend(relativeTime);
        resetClock();
    }

    const resetClock = function(){
        refreshPostTimes();
        clockTime();
    }

    const refreshPostTimes = function () {
        $(".folio-comm-post-time:not('.processed')").each(function(){
            var _this = $(this);
            postTimes.push({ id: _this.attr('id'), time: dayjs( _this.attr('datetime') ) });
            _this.addClass('processed');
        });
    }

    /**
     * Update time clock every min
     */
    const clockTime = function(){
        if (updateTimeout){
            clearTimeout(updateTimeout);
        }

        postTimes.forEach(function(obj) {
            $('#' + obj.id).text(obj.time.fromNow());
        });

        refreshTimeDisplay.text(refreshTime.fromNow());

        updateTimeout = setTimeout(clockTime, 60000);
    }

    /** 
     * Search community 
     */
    new LoadMore('search');

    /**
     * Recent activity
     */
    new LoadMore('pubs');

    setupClock();


} )( jQuery, typeof folioCommunityData !== 'undefined' ? folioCommunityData : {})