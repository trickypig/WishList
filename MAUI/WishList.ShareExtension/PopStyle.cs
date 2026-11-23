using CoreGraphics;
using UIKit;

namespace WishList.ShareExtension;

/// <summary>The app's Bold Pop look (Resources/Styles/Colors.xaml) for the share sheet's UIKit views.</summary>
public static class PopStyle
{
    public static readonly UIColor Bg = Dynamic("#fffbea", "#16161a");
    public static readonly UIColor Surface = Dynamic("#ffffff", "#24242b");
    public static readonly UIColor Ink = Dynamic("#111111", "#fffbea");
    public static readonly UIColor Subtle = Dynamic("#4a4a4a", "#b9b6ad");
    public static readonly UIColor Highlight = Hex("#ffe14d");
    public static readonly UIColor Accent = Hex("#ff5c8a");
    public static readonly UIColor Danger = Hex("#ff4d4d");
    public static readonly UIColor Success = Hex("#b7f171");
    /// <summary>Text on the bright fills (yellow, pink, green) stays dark in both themes.</summary>
    public static readonly UIColor OnColor = Hex("#111111");

    public static UIFont Bold(nfloat size) => UIFont.BoldSystemFontOfSize(size)!;
    public static UIFont Regular(nfloat size) => UIFont.SystemFontOfSize(size)!;

    /// <summary>2px ink border, rounded corners, hard 3px offset shadow.</summary>
    public static void Pop(UIView view, UIColor fill, bool shadow = true, nfloat? radius = null)
    {
        view.BackgroundColor = fill;
        view.Layer.CornerRadius = radius ?? 10;
        view.Layer.BorderWidth = 2;
        view.Layer.ShadowOffset = shadow ? new CGSize(3, 3) : CGSize.Empty;
        view.Layer.ShadowRadius = 0;
        view.Layer.ShadowOpacity = shadow ? 1 : 0;
        RefreshLayerColors(view);
    }

    /// <summary>Layer colors don't follow dark mode by themselves; call again when the theme changes.</summary>
    public static void RefreshLayerColors(UIView view)
    {
        var ink = Ink.GetResolvedColor(view.TraitCollection).CGColor;
        view.Layer.BorderColor = ink;
        view.Layer.ShadowColor = ink;
    }

    public static UIColor Hex(string hex)
    {
        var v = Convert.ToUInt32(hex.TrimStart('#'), 16);
        return UIColor.FromRGB((byte)(v >> 16), (byte)(v >> 8), (byte)v);
    }

    static UIColor Dynamic(string light, string dark)
    {
        UIColor l = Hex(light), d = Hex(dark);
        return UIColor.FromDynamicProvider(t => t.UserInterfaceStyle == UIUserInterfaceStyle.Dark ? d : l);
    }
}
