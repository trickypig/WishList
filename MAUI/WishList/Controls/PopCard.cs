using Microsoft.Maui.Controls.Shapes;

namespace WishList.Controls;

/// <summary>
/// Bold Pop card: an outlined surface with a hard, unblurred offset shadow.
/// MAUI's Shadow always blurs, so the shadow is a second shape drawn behind
/// the card and shifted by <see cref="Offset"/>. Ink and surface colors follow
/// the light/dark theme; <see cref="Fill"/> overrides the surface color.
/// </summary>
public class PopCard : ContentView
{
    public static readonly BindableProperty FillProperty =
        BindableProperty.Create(nameof(Fill), typeof(Color), typeof(PopCard), null, propertyChanged: (b, _, _) => ((PopCard)b).ApplyFill());

    public static readonly BindableProperty OffsetProperty =
        BindableProperty.Create(nameof(Offset), typeof(double), typeof(PopCard), 4.0, propertyChanged: (b, _, _) => ((PopCard)b).ApplyGeometry());

    public static readonly BindableProperty CornerRadiusProperty =
        BindableProperty.Create(nameof(CornerRadius), typeof(double), typeof(PopCard), 14.0, propertyChanged: (b, _, _) => ((PopCard)b).ApplyGeometry());

    public static readonly BindableProperty InnerPaddingProperty =
        BindableProperty.Create(nameof(InnerPadding), typeof(Thickness), typeof(PopCard), new Thickness(16), propertyChanged: (b, _, n) => ((PopCard)b)._front.Padding = (Thickness)n);

    public static readonly BindableProperty OutlineColorProperty =
        BindableProperty.Create(nameof(OutlineColor), typeof(Color), typeof(PopCard), null, propertyChanged: (b, _, _) => ((PopCard)b).ApplyOutline());

    /// <summary>Card background; null means the theme surface color.</summary>
    public Color? Fill { get => (Color?)GetValue(FillProperty); set => SetValue(FillProperty, value); }

    /// <summary>Shadow offset (both axes). 4 for cards, 5 for heroes.</summary>
    public double Offset { get => (double)GetValue(OffsetProperty); set => SetValue(OffsetProperty, value); }

    public double CornerRadius { get => (double)GetValue(CornerRadiusProperty); set => SetValue(CornerRadiusProperty, value); }

    /// <summary>Padding inside the outlined card.</summary>
    public Thickness InnerPadding { get => (Thickness)GetValue(InnerPaddingProperty); set => SetValue(InnerPaddingProperty, value); }

    /// <summary>Outline color override (e.g. accent while a drag hovers); null means theme ink.</summary>
    public Color? OutlineColor { get => (Color?)GetValue(OutlineColorProperty); set => SetValue(OutlineColorProperty, value); }

    readonly Border _shadow = new() { StrokeThickness = 0, InputTransparent = true };
    readonly Border _front = new() { StrokeThickness = 2.5, Padding = new Thickness(16) };
    readonly Grid _root = new();

    public PopCard()
    {
        _root.Children.Add(_shadow);
        _root.Children.Add(_front);
        _shadow.SetAppThemeColor(BackgroundColorProperty, Ink(light: true), Ink(light: false));
        ApplyOutline();
        ApplyFill();
        ApplyGeometry();
        ControlTemplate = new ControlTemplate(() =>
        {
            _front.Content = new ContentPresenter();
            return _root;
        });
    }

    void ApplyGeometry()
    {
        var shape = new RoundRectangle { CornerRadius = CornerRadius };
        _front.StrokeShape = shape;
        _shadow.StrokeShape = new RoundRectangle { CornerRadius = CornerRadius };
        _shadow.TranslationX = Offset;
        _shadow.TranslationY = Offset;
        // Leave room inside our bounds so the shifted shadow isn't clipped by neighbours
        _root.Padding = new Thickness(0, 0, Offset, Offset);
    }

    void ApplyFill()
    {
        if (Fill is { } fill)
        {
            _front.RemoveBinding(BackgroundColorProperty);
            _front.BackgroundColor = fill;
        }
        else
        {
            _front.SetAppThemeColor(BackgroundColorProperty, Res("SurfaceLight", Colors.White), Res("SurfaceDark", Color.FromArgb("#24242b")));
        }
    }

    void ApplyOutline()
    {
        if (OutlineColor is { } c)
            _front.Stroke = c;
        else
            _front.SetAppTheme<Brush>(Border.StrokeProperty, new SolidColorBrush(Ink(light: true)), new SolidColorBrush(Ink(light: false)));
    }

    static Color Ink(bool light) => light ? Res("InkLight", Color.FromArgb("#111111")) : Res("InkDark", Color.FromArgb("#fffbea"));

    static Color Res(string key, Color fallback) =>
        Application.Current?.Resources.TryGetValue(key, out var v) == true && v is Color c ? c : fallback;
}
