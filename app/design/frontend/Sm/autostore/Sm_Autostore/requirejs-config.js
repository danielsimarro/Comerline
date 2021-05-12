var config = {
    map: {
        '*': {
            jquerypopper: "Sm_Autostore/js/bootstrap/popper",
            jquerybootstrap: "Sm_Autostore/js/bootstrap/bootstrap.min",
            owlcarousel: "Sm_Autostore/js/owl.carousel",
            slick: "Sm_Autostore/js/slick",
            jqueryfancyboxpack: "Sm_Autostore/js/jquery.fancybox.pack",
            jqueryunveil: "Sm_Autostore/js/jquery.unveil",
            yttheme: "Sm_Autostore/js/yttheme"
        }
    },
    shim: {
        'jquerypopper': {
            'deps': ['jquery'],
            'exports': 'Popper'
        },
        'jquerybootstrap': {
            'deps': ['jquery', 'jquerypopper']
        }
    }
};