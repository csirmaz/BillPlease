
BP = {
   
   // Functions to interact with the cookie storing UI data
   Storage: { 
      data: (Cookies.getJSON('storage') || {}),
      get: function(key, defvalue){ return (typeof(BP.Storage.data[key]) == 'undefined' ? defvalue : BP.Storage.data[key]); },
      set: function(key, value){ BP.Storage.data[key] = value; Cookies.set('storage', BP.Storage.data); },
      del: function(key){ delete BP.Storage.data[key]; Cookies.set('storage', BP.Storage.data); }
   },

   // Check if a string is a float
   checkfl: function(v, msg){
      if(v.match(/^\-?[\d\.]+$/)){ // returns null?
         if(v.match(/\./g).length<=1){
            return parseFloat(v);
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

   checkintlist: function(v, msg){
      if(v.match(/^[\d,]+$/)){ return true; }
      alert(msg + ' is not a list of integers');
      return false;
   }

};


// Initialisations
$(function(){
   
    var $bpt = $('#bplist_tools');
   
    if($bpt.length){ // Only on list pages
            
        // Add space to make content visible under the fixed tools area
        var fixtools = function(){
            $('#bplist').css('padding-bottom', $bpt.height() + 'px');
        };
        fixtools();
        setInterval(fixtools, 2000);

        // Sticky scroll position
        $('body').on('click', function(){
            BP.Storage.set('listscroll', document.body.scrollTop); // Save scroll position
        });
        // Initialise scroll position
        document.body.scrollTop = BP.Storage.get('listscroll', $('#bplist').height());

        // Search
        $('.searchform a').on('click', function(){
            document.location.search = 'view=search&t=' + encodeURIComponent($('.searchform input').val());
        });

        // Modify an item
        $('.bpitem .bpname').on('click', function(e){
            document.location.search = 'view=edit&mode=modify&id=' + $(this).closest('.bpitem').data('id'); 
            e.preventDefault();
        });

        // Add new entry on given day
        $('.bpheader .bpname').on('click', function(e){
            document.location.search = 'view=edit&mode=new-on&ud=' + $(this).closest('.bpheader').data('uday'); 
            e.preventDefault();
        });
        
        // Toggle the checked status of an item
        $('.bpitem .bpchkd_in').on('click', function(e){
            document.location.search = 'action=check&id=' + $(this).closest('.bpitem').data('id'); 
            e.preventDefault();
        });

    } // Only on list pages -- ends
      

    // Navigating in time
    $('.bpnavigate').on('click', function(e){
        var $this = $(this);
        var o = BP.Storage.get('dayoffset');
        o -= (-$this.data('offset'));
        var absolute = $this.data('absolute');
        if(typeof(absolute) != 'undefined'){ o = absolute; }
        BP.Storage.set('dayoffset', o);
        document.location.search = 'view=list';
        e.preventDefault();
   });
   
   $('.calculator').on('click', function(){
      var s = 0;
      var x;
      while(true){
         x = prompt("Sum so far: "+(s/100)+". Enter a number to add, 'c' to copy the sum, or 'a' to abort");
         if(x=='c'){
            $('.calculator_value').val(Math.floor(s)/100);
            break;
         }
         if(x=='a'){ break; }
         s += Math.floor(x*100+.5);
      }
   });

});