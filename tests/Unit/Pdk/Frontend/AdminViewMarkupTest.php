
it('joins the boot element, notifications and modals into a shell', function () {
    expect(AdminViewMarkup::composeShell(AdminView::OrderList, '<span>', '<n>', '<m>'))->toBe('<span><n><m>');
});

it('fails to build a shell without a boot element', function () {
    expect(fn () => AdminViewMarkup::composeShell(AdminView::OrderList, '', '<n>', '<m>'))
        ->toThrow(EmptyAdminViewException::class, 'orderList');
});
