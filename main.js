
BP = {
   
    // Functions to interact with the cookie storing UI data
    Storage: { 
        data: (Cookies.getJSON('storage') || {}),
        get: function(key, defvalue){ return (typeof(BP.Storage.data[key]) == 'undefined' ? defvalue : BP.Storage.data[key]); },
        set: function(key, value){ BP.Storage.data[key] = value; Cookies.set('storage', BP.Storage.data); },
        del: function(key){ delete BP.Storage.data[key]; Cookies.set('storage', BP.Storage.data); }
    },

    // Check if a string is a float (and has a precision of 1/100)
    checkfl: function(v, msg) {
        if(v.match(/^\-?[\d\.]+$/)) { // returns null?
            var vv = v.match(/\./g);
            if((!vv) || vv.length<=1) {
                n = parseFloat(v);
                if(n === false) {
                    alert(msg + ' is not a number');
                    return false;
                }
                if(Math.abs(Math.floor(n*100+.5) - n*100) > 1e-8) {
                    console.log('v', v, 'n', n, Math.floor(n*100), n*100);
                    if(confirm(msg + ' is not in the right format. Use?')) { return n; }
                    return false;
                }
                return n;
            }
        }
        alert(msg + ' is not a number');
        return false;
    },

   // Check if a string is an integer
   checkint: function(v, msg){
      if(v.match(/^\d+$/)){ return parseInt(v); }
      alert(msg + ' is not an integer number');
      return false;
   },
   
    // Show the editing modal
    edit_modal: function(mode, key, value) {
        var $modal = $('.bp-edit-modal');
        var $body = $('.bp-edit-modal .modal-body');
        $body.html('Loading...');
        var data = {
                view: 'edit',
                mode: mode
        };
        if(key){ data[key] = value; }
        $.ajax({
            url: '?',
            method: 'POST',
            data: data,
            success: function(content){
                $body.html(content);
                $modal.modal('handleUpdate');
                
                var iform = document.bp_itemedit;
                iform.onsubmit = function(){ return false; }
                iform.name.onblur = bp_edit_namecomplete;
                iform.day.select();
            }
        });
        $modal.modal({keyboard:true});
    },
    
    // Send the edit form to create or modify an entry
    send_edit: function() {
        var o = document.bp_itemedit;
        
        var x;
        x = BP.checkint(o.day.value, 'Day');
        if(x === false){ return false; }
        if(x < 1){ alert('Day is less than 1'); return false; }
        if(x > 31){ alert('Day is greater than 31'); return false; }

        x = BP.checkint(o.month.value, 'Month');
        if(x === false){ return false; }
        if(x < 1){ alert('Month is less than 1'); return false; }
        if(x > 12){ alert('Month is greater than 12'); return false; }

        x = BP.checkint(o.year.value, 'Year');
        if(x === false){ return false; }
        if(x < 1900){ alert('Year is less than 1900'); return false; }
        if(x > 2100){ alert('Year is greater than 2100'); return false; }

        if(BP.checkfl(o.value.value, 'Price') === false){
            return false;
        }

        BP.do_action($(o).serialize() + '&action=add');
    },
    
    show_error: function(message) {
        BP.show_curtain();
        $('.modal').modal('hide'); // Hide all modals
        $('.bp-error-modal').modal();
        $('.bp-error-modal .bp-error-details').text(message || 'unknown');
    },
    
    // Send an AJAX request to complete an action
    do_action: function(request) {
        BP.show_curtain();
        $.ajax({
            url: '?',
            method: 'POST',
            data: request,
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    document.location.reload();
                }
                else {
                    BP.show_error(response.message);
                }
            },
            error: function(jqxhr, status, err){ BP.show_error(status); }
        });        
    },
    
    show_curtain: function(){ $('.bp-curtain').show(); }

};


// Initialisations
$(function(){
   
    var $bpt = $('#bplist_tools');
   
    // Add space to make content visible under the fixed tools area
    var fixtools = function(){
        $('#bplist').css('padding-bottom', $bpt.height() + 'px');
    };
    fixtools();
    setInterval(fixtools, 2000);

    // Sticky scroll position
    $('body').on('click', function() {
        BP.Storage.set('listscroll', document.body.scrollTop); // Save scroll position
    });
    // Initialise scroll position
    document.body.scrollTop = BP.Storage.get('listscroll', $('#bplist').height());
    
    // Shortcut keys
    $('body').on('keydown', function(e) {
        switch(e.which){
            case 78: // n
                BP.edit_modal('new');
                break;
        }
    });
        
    // Search
    var submit_search = function() { 
        document.location.search = 'view=search&t=' + encodeURIComponent($('.searchform input').val()); 
    };
    $('.searchform .bp-do-search').on('click', submit_search);
    $('.searchform input').on('keydown', function(e){ 
        e.stopPropagation();
        if (e.which == 13) { submit_search(); }
    });

    // Search help
    $('.bp-search-help-t').on('click', function(e){
        $('.bp-search-help').slideToggle();
        e.preventDefault();
    });

    // New entry
    $('.bp-newentry').on('click', function(e){
        BP.edit_modal('new');
        e.preventDefault();
    });

    // Modify an entry
    $('.bpitem .bpname').on('click', function(e){
        BP.edit_modal('modify', 'id', $(this).closest('.bpitem').data('id'));
        e.preventDefault();
    });

    // Add new entry on given day
    $('.bpheader .bpname').on('click', function(e){
        BP.edit_modal('new-on', 'ud', $(this).closest('.bpheader').data('uday'));
        e.preventDefault();
    });

    // Toggle the checked status of an item
    $('.bpitem .bpchkd_in').on('click', function(e){
        BP.do_action({action:'check', id:$(this).closest('.bpitem').data('id')});
        e.preventDefault();
    });
        
    // Toggle the business status of an item
    $('.bpitem .bpbusinessi').on('click', function(e){
        BP.do_action({action:'business', id:$(this).closest('.bpitem').data('id')});
        e.preventDefault();
    });
        
    // Get long items
    $('.bp-getlongitems-action').on('click', function(e){
        var $body = $('.bp-long-items-modal .modal-body');
        $body.html('Loading...');
        $.ajax({
            url: '?',
            data: {
                view:'longitems',
                ud: $(e.target).data('ud')
            },
            success: function(data){
                $body.html(data);
            }
        });
        $('.bp-long-items-modal').modal({keyboard:true});
    });

    // Navigating in time
    $('.bpnavigate').on('click', function(e){
        var $this = $(this);
        var o = BP.Storage.get('dayoffset');
        if(isNaN(o)){ o = 0; }
        o -= (-$this.data('offset'));
        var absolute = $this.data('absolute');
        if(typeof(absolute) != 'undefined'){ o = absolute; }
        BP.Storage.set('dayoffset', o);
        document.location.search = 'view=list';
        e.preventDefault();
    });
    
    // Get the first unchecked entries
    $('.bp-firstchecked-button').on('click', function(e){ $('.bp-firstchecked-modal').modal({keyboard:true}); });

    // ----- Edit modal -----
    
    var $editmodal = $('.bp-edit-modal');
    
    // A simple calculator to add multiple values
    $editmodal.on('click', '.bp-calculator', undefined, function(){
        var s = 0;
        var x;
        while(true){
            x = prompt("ADD VALUES\nSum so far: "+(s/100)+"\n  Enter a number to add,\n  or enter 'c' to copy the sum to the form.\n  Click Cancel to abort.");
            if(x=='c'){
                $('.bp-calculator_value').val(Math.floor(s)/100);
                break;
            }
            if(x===null){ break; }
            s += Math.floor(x*100+.5);
        }
    });
    
    // Delete button
    $editmodal.on('click', '.bp-edit-delete', undefined, function() {
        var id = document.bp_itemedit.oldid.value;
        if(id<0){ 
            alert('Cannot delete a new entry'); 
        }
        else{
            if(confirm('Are you sure you want to delete this item?')) {
                BP.do_action({action:'delete', id:id});
            }
        }
        return false;
    });
    
    // Create / modify button
    $editmodal.on('click', '.bp-edit-send', undefined, function(){
        BP.send_edit();
        return false;
    });
    
    // Load sortcuts
    $editmodal.on('click', '.bp-shortcuts-load', undefined, function(){
        $('.bp-edit-help').slideDown();
        $.ajax({
            url: '?',
            method: 'POST',
            data: {view:'shortcuts'},
            success: function(content){
                $('.bp-shortcuts').html(content);
                $editmodal.modal('handleUpdate');
            }
        });        
        return false;
    });

    // Shortcut keys
    $editmodal.on('keydown', function(e) {
        e.stopPropagation(); // to avoid global shortcut keys getting triggered
        switch(e.which){
            case 13: // ENTER
                if(e.ctrlKey){ BP.send_edit(); }
                break;
        }
    });

});


