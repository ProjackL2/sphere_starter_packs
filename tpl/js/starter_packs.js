$(document).on("click", ".openWindowBuyPack", function () {
    let name = $(this).data("pack-name");
    let cost = $(this).data("pack-cost");
    let product_id = $(this).data("product-id");

    $("#user_name_buy").text(name);
    $("#user_worth_buy").text(cost);
    $('#buy_pack').data('product-id', product_id);
});

$(document).on("click", "#buy_pack", function (event) {
    event.preventDefault();
    
    $.ajax({
        type: "POST",
        url: "/starter_packs/buy",
        data: {
            server_id: $(this).data("server_id"),
            product_id: $(this).data("product-id"),
            char_name: $("#char_name").val(),
        },
        dataType: "json",
        encode: true,
    }).done(function (data) {
        $('#modal_panel_apply').modal('toggle');
        if (data.ok) {
            notify_success(data.message);
            $(".count_sphere_coin").text(data.donate_bonus);
        } else {
            notify_error(data.message);
        }
    });
});
