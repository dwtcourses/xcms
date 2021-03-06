// Generated by CoffeeScript 1.10.0
describe('ContentTools.ImageDialog', function() {
  var div, editor;
  div = null;
  editor = null;
  beforeEach(function() {
    div = document.createElement('div');
    div.setAttribute('class', 'editable');
    document.body.appendChild(div);
    editor = ContentTools.EditorApp.get();
    return editor.init('.editable');
  });
  afterEach(function() {
    editor.destroy();
    return document.body.removeChild(div);
  });
  describe('ContentTools.ImageDialog()', function() {
    return it('should return an instance of a ImageDialog', function() {
      var dialog;
      dialog = new ContentTools.ImageDialog();
      return expect(dialog instanceof ContentTools.ImageDialog).toBe(true);
    });
  });
  return describe('ContentTools.ImageDialog.cropRegion()', function() {
    return it('should return the crop region set by the user', function() {
      var dialog;
      dialog = new ContentTools.ImageDialog();
      editor.attach(dialog);
      dialog.mount();
      expect(dialog.cropRegion()).toEqual([0, 0, 1, 1]);
      dialog._domView.style.width = '400px';
      dialog._domView.style.height = '400px';
      dialog.populate('test.png', [400, 400]);
      dialog.addCropMarks();
      dialog._cropMarks._domHandles[1].style.left = '200px';
      dialog._cropMarks._domHandles[1].style.top = '200px';
      return expect(dialog.cropRegion()).toEqual([0, 0, 0.5, 0.5]);
    });
  });
});
