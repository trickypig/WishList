using CoreGraphics;
using UIKit;
using WishList.Helpers;
using WishList.Models;
using WishList.Share;

namespace WishList.ShareExtension;

/// <summary>
/// The "Add to Wish List" sheet: preview card, name/price/note fields, the user's lists, and Save.
/// Pure layout and display; ShareViewController decides what happens.
/// </summary>
public sealed class ShareSheetView : UIView
{
    public event Action? CancelTapped;
    public event Action? SaveTapped;
    public event Action<string>? NameEdited;
    public event Action<string>? PriceEdited;
    public event Action<string>? NoteEdited;
    public event Action<WishListModel>? ListSelected;

    readonly UIImageView _image = new();
    readonly UILabel _previewName = new();
    readonly UILabel _previewSite = new();
    readonly UITextField _name = Field("Name");
    readonly UITextField _price = Field("Price");
    readonly UITextField _note = Field("Note: e.g. size, color…");
    readonly UILabel _saveToLabel = new();
    readonly UIStackView _lists = new() { Axis = UILayoutConstraintAxis.Vertical, Spacing = 6 };
    readonly UILabel _status = new() { Lines = 0, TextAlignment = UITextAlignment.Center };
    readonly UIButton _save = new(UIButtonType.System);
    readonly UIActivityIndicatorView _spinner = new(UIActivityIndicatorViewStyle.Medium);
    readonly List<UIView> _popped = [];

    public ShareSheetView()
    {
        BackgroundColor = PopStyle.Bg;

        var cancel = new UIButton(UIButtonType.System);
        cancel.SetTitle("Cancel", UIControlState.Normal);
        cancel.SetTitleColor(PopStyle.Subtle, UIControlState.Normal);
        cancel.TitleLabel.Font = PopStyle.Regular(16);
        cancel.TouchUpInside += (_, _) => CancelTapped?.Invoke();

        var title = new UILabel { Text = "Add to Wish List", Font = PopStyle.Bold(17), TextColor = PopStyle.Ink, TextAlignment = UITextAlignment.Center };
        var header = new UIView();
        foreach (var v in new UIView[] { cancel, title }) { v.TranslatesAutoresizingMaskIntoConstraints = false; header.AddSubview(v); }
        NSLayoutConstraint.ActivateConstraints([
            cancel.LeadingAnchor.ConstraintEqualTo(header.LeadingAnchor),
            cancel.CenterYAnchor.ConstraintEqualTo(header.CenterYAnchor),
            title.CenterXAnchor.ConstraintEqualTo(header.CenterXAnchor),
            title.TopAnchor.ConstraintEqualTo(header.TopAnchor),
            title.BottomAnchor.ConstraintEqualTo(header.BottomAnchor),
            title.LeadingAnchor.ConstraintGreaterThanOrEqualTo(cancel.TrailingAnchor, 8),
        ]);

        // Preview card
        _image.ContentMode = UIViewContentMode.ScaleAspectFill;
        _image.ClipsToBounds = true;
        _image.Layer.CornerRadius = 6;
        _image.Layer.BorderWidth = 2;
        _image.BackgroundColor = PopStyle.Hex(BoldPop.TileHex[0]);
        _image.TranslatesAutoresizingMaskIntoConstraints = false;
        NSLayoutConstraint.ActivateConstraints([
            _image.WidthAnchor.ConstraintEqualTo(56),
            _image.HeightAnchor.ConstraintEqualTo(56),
        ]);
        _previewName.Font = PopStyle.Bold(15);
        _previewName.TextColor = PopStyle.Ink;
        _previewName.Lines = 2;
        _previewSite.Font = PopStyle.Regular(13);
        _previewSite.TextColor = PopStyle.Subtle;
        var siteRow = new UIStackView([_previewSite, _spinner]) { Axis = UILayoutConstraintAxis.Horizontal, Spacing = 6, Alignment = UIStackViewAlignment.Center };
        var previewText = new UIStackView([_previewName, siteRow]) { Axis = UILayoutConstraintAxis.Vertical, Spacing = 2, Alignment = UIStackViewAlignment.Leading };
        var card = new UIStackView([_image, previewText]) { Axis = UILayoutConstraintAxis.Horizontal, Spacing = 10, Alignment = UIStackViewAlignment.Center };
        card.LayoutMarginsRelativeArrangement = true;
        card.LayoutMargins = new UIEdgeInsets(8, 8, 8, 8);
        Popped(card, PopStyle.Surface);

        // Fields
        _price.KeyboardType = UIKeyboardType.DecimalPad;
        _name.EditingChanged += (_, _) => { NameEdited?.Invoke(_name.Text ?? ""); _previewName.Text = _name.Text; };
        _price.EditingChanged += (_, _) => PriceEdited?.Invoke(_price.Text ?? "");
        _note.EditingChanged += (_, _) => NoteEdited?.Invoke(_note.Text ?? "");
        foreach (var f in new[] { _name, _price, _note })
        {
            f.ShouldReturn = tf => { tf.ResignFirstResponder(); return true; };
            Popped(f, PopStyle.Surface, shadow: false, radius: 8);
        }
        var nameRow = new UIStackView([_name, _price]) { Axis = UILayoutConstraintAxis.Horizontal, Spacing = 6 };
        _price.WidthAnchor.ConstraintEqualTo(96).Active = true;

        _saveToLabel.Text = "SAVE TO";
        _saveToLabel.Font = PopStyle.Bold(11);
        _saveToLabel.TextColor = PopStyle.Subtle;

        _status.Font = PopStyle.Bold(14);
        _status.Hidden = true;

        _save.SetTitle("Save", UIControlState.Normal);
        _save.SetTitleColor(PopStyle.OnColor, UIControlState.Normal);
        _save.SetTitleColor(PopStyle.OnColor.ColorWithAlpha(0.4f), UIControlState.Disabled);
        _save.TitleLabel.Font = PopStyle.Bold(16);
        _save.HeightAnchor.ConstraintEqualTo(46).Active = true;
        _save.TouchUpInside += (_, _) => SaveTapped?.Invoke();
        Popped(_save, PopStyle.Accent);

        var content = new UIStackView([header, card, nameRow, _note, _saveToLabel, _lists, _status, _save])
        {
            Axis = UILayoutConstraintAxis.Vertical,
            Spacing = 10,
            TranslatesAutoresizingMaskIntoConstraints = false
        };
        content.SetCustomSpacing(16, header);
        content.SetCustomSpacing(14, card);
        content.SetCustomSpacing(4, _saveToLabel);
        content.SetCustomSpacing(14, _lists);

        var scroll = new UIScrollView { TranslatesAutoresizingMaskIntoConstraints = false, KeyboardDismissMode = UIScrollViewKeyboardDismissMode.Interactive };
        scroll.AddSubview(content);
        AddSubview(scroll);
        NSLayoutConstraint.ActivateConstraints([
            scroll.TopAnchor.ConstraintEqualTo(SafeAreaLayoutGuide.TopAnchor),
            scroll.LeadingAnchor.ConstraintEqualTo(LeadingAnchor),
            scroll.TrailingAnchor.ConstraintEqualTo(TrailingAnchor),
            // Keep Save above the keyboard
            scroll.BottomAnchor.ConstraintEqualTo(KeyboardLayoutGuide.TopAnchor),
            content.TopAnchor.ConstraintEqualTo(scroll.ContentLayoutGuide.TopAnchor, 14),
            content.BottomAnchor.ConstraintEqualTo(scroll.ContentLayoutGuide.BottomAnchor, -18),
            content.LeadingAnchor.ConstraintEqualTo(scroll.FrameLayoutGuide.LeadingAnchor, 16),
            content.TrailingAnchor.ConstraintEqualTo(scroll.FrameLayoutGuide.TrailingAnchor, -16),
        ]);

        SetSaveEnabled(false);
    }

    /// <summary>Show the draft's current values. User edits are already in the draft, so this never loses typing.</summary>
    public void ShowDraft(ShareDraft draft)
    {
        if (_name.Text != draft.Name) _name.Text = draft.Name;
        if (_price.Text != draft.PriceText) _price.Text = draft.PriceText;
        _previewName.Text = string.IsNullOrWhiteSpace(draft.Name) ? AllowedSites.HostOf(draft.Url) : draft.Name;
        _previewSite.Text = string.IsNullOrWhiteSpace(draft.StoreName) ? AllowedSites.HostOf(draft.Url) : draft.StoreName;
    }

    public void SetImage(UIImage image) => _image.Image = image;

    public void SetLoadingDetails(bool loading)
    {
        if (loading) _spinner.StartAnimating(); else _spinner.StopAnimating();
    }

    public void SetLists(IReadOnlyList<WishListModel> lists, WishListModel? selected)
    {
        foreach (var old in _lists.ArrangedSubviews) { _lists.RemoveArrangedSubview(old); old.RemoveFromSuperview(); _popped.Remove(old); }

        foreach (var list in lists)
        {
            var isSelected = list.Id == selected?.Id;
            var row = new UIButton(UIButtonType.Custom);
            row.SetTitle(isSelected ? $"{list.Title}  ✓" : list.Title, UIControlState.Normal);
            row.SetTitleColor(isSelected ? PopStyle.OnColor : PopStyle.Ink, UIControlState.Normal);
            row.TitleLabel.Font = isSelected ? PopStyle.Bold(15) : PopStyle.Regular(15);
            row.HorizontalAlignment = UIControlContentHorizontalAlignment.Left;
            row.ContentEdgeInsets = new UIEdgeInsets(0, 34, 0, 10);
            row.HeightAnchor.ConstraintEqualTo(42).Active = true;

            var dot = new UIView { UserInteractionEnabled = false, TranslatesAutoresizingMaskIntoConstraints = false };
            dot.BackgroundColor = PopStyle.Hex(list.ListColorHex);
            dot.Layer.CornerRadius = 7;
            dot.Layer.BorderWidth = 2;
            row.AddSubview(dot);
            NSLayoutConstraint.ActivateConstraints([
                dot.WidthAnchor.ConstraintEqualTo(14),
                dot.HeightAnchor.ConstraintEqualTo(14),
                dot.LeadingAnchor.ConstraintEqualTo(row.LeadingAnchor, 11),
                dot.CenterYAnchor.ConstraintEqualTo(row.CenterYAnchor),
            ]);
            dot.Layer.BorderColor = PopStyle.OnColor.CGColor;

            row.TouchUpInside += (_, _) => ListSelected?.Invoke(list);
            Popped(row, isSelected ? PopStyle.Highlight : PopStyle.Surface, shadow: false, radius: 8);
            _lists.AddArrangedSubview(row);
        }

        _saveToLabel.Hidden = lists.Count == 0;
    }

    /// <summary>A message under the lists; null hides it.</summary>
    public void SetStatus(string? message, bool isError = false)
    {
        _status.Text = message;
        _status.TextColor = isError ? PopStyle.Danger : PopStyle.Ink;
        _status.Hidden = string.IsNullOrEmpty(message);
    }

    public void SetSaveEnabled(bool enabled)
    {
        _save.Enabled = enabled;
        _save.Alpha = enabled ? 1 : 0.5f;
    }

    public void SetFieldsEnabled(bool enabled)
    {
        foreach (var f in new[] { _name, _price, _note }) f.Enabled = enabled;
        foreach (var row in _lists.ArrangedSubviews.OfType<UIButton>()) row.Enabled = enabled;
        if (!enabled) this.EndEditing(true);
    }

    public override void TraitCollectionDidChange(UITraitCollection? previousTraitCollection)
    {
        base.TraitCollectionDidChange(previousTraitCollection);
        foreach (var v in _popped) PopStyle.RefreshLayerColors(v);
        _image.Layer.BorderColor = PopStyle.Ink.GetResolvedColor(TraitCollection).CGColor;
    }

    public override void MovedToWindow()
    {
        base.MovedToWindow();
        TraitCollectionDidChange(null);
    }

    void Popped(UIView view, UIColor fill, bool shadow = true, nfloat? radius = null)
    {
        PopStyle.Pop(view, fill, shadow, radius);
        _popped.Add(view);
    }

    static UITextField Field(string placeholder)
    {
        var f = new UITextField
        {
            Font = PopStyle.Regular(15),
            TextColor = PopStyle.Ink,
            AttributedPlaceholder = new Foundation.NSAttributedString(placeholder, foregroundColor: PopStyle.Subtle),
            ReturnKeyType = UIReturnKeyType.Done,
            LeftView = new UIView(new CGRect(0, 0, 10, 1)),
            LeftViewMode = UITextFieldViewMode.Always,
            ClearButtonMode = UITextFieldViewMode.WhileEditing
        };
        f.HeightAnchor.ConstraintEqualTo(42).Active = true;
        return f;
    }
}
