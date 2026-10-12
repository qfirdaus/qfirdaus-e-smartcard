
(function ($){
    'use strict';
    function initComponents(){
        $(window).on('load', function (){
            $('#status').fadeOut();
            $('#preloader').delay(350).fadeOut('slow');
        });
        
        var popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
        var popoverList = Array.prototype.slice.call(popoverTriggerList).map(function(popoverTriggerEl){
            return new bootstrap.Popover(popoverTriggerEl);
        });
        
        var tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        var tooltipList = Array.prototype.slice.call(tooltipTriggerList).map(function(tooltipTriggerEl){
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        var offcanvasElementList = document.querySelectorAll('.offcanvas');
        var offcanvasList = Array.prototype.slice.call(offcanvasElementList).map(function(offcanvasEl){
            return new bootstrap.Offcanvas(offcanvasEl);
        });

        var toastPlacement = document.getElementById("toastPlacement");
        if (toastPlacement){
            document.getElementById("selectToastPlacement").addEventListener("change", function (){
                if (!toastPlacement.dataset.originalClass){
                    toastPlacement.dataset.originalClass = toastPlacement.className;
                }
                toastPlacement.className = toastPlacement.dataset.originalClass + " " + this.value;
            });
        }

        var toastElList = [].slice.call(document.querySelectorAll('.toast'));
        var toastList = toastElList.map(function (toastEl){
            return new bootstrap.Toast(toastEl);
        });
    }

    function init(){
        initComponents();
    }

    init();
})(jQuery);
