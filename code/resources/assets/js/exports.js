require('jquery-ui/ui/widgets/draggable');
require('jquery-ui/ui/widgets/droppable');

class Exports {
    static init(container)
    {
		$('#import_csv_sorter .im_draggable', container).each(function() {
            $(this).draggable({
                helper: 'clone',
                revert: 'invalid'
            });
        });

        $('#import_csv_sorter .im_droppable', container).droppable({
			over: function() {
				$(this).addClass('bg-success text-white');
			},
			out: function() {
				$(this).removeClass('bg-success text-white');
			},
            drop: function(event, ui) {
                var node = ui.draggable.clone();
                node.find('input:hidden').attr('name', 'column[]');
                $(this).removeClass('bg-success text-white').find('.column_content').empty().append(node.contents());
            }
        });
	}
}

export default Exports;
