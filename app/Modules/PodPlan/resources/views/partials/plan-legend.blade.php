{{-- What the marks on the plan mean. Shared by the Plan screen and the secret-link page. --}}
<div class="pp-legend">
    <span><i style="width:14px;height:10px;border-radius:2px;background:var(--pp-accent);"></i>{{ __('podplan::messages.plan.legend_camera') }}</span>
    <span><i style="width:12px;height:12px;border-radius:50%;background:var(--pp-lamp);border:2px solid var(--pp-ink);"></i>{{ __('podplan::messages.plan.legend_light') }}</span>
    <span><i style="width:18px;border-top:2px dashed var(--pp-live);"></i>{{ __('podplan::messages.plan.legend_hdmi') }}</span>
    <span><i style="width:18px;border-top:2px solid var(--pp-ok);"></i>{{ __('podplan::messages.plan.legend_xlr') }}</span>
    <span><svg width="18" height="13" viewBox="0 0 18 13" aria-hidden="true"><circle cx="9" cy="10.7" r="1.7" style="fill:var(--pp-muted)"/><path d="M5 7.7 A5.6 5.6 0 0 1 13 7.7" style="fill:none;stroke:var(--pp-muted);stroke-width:1.8;stroke-linecap:round"/><path d="M1.6 4.3 A10.4 10.4 0 0 1 16.4 4.3" style="fill:none;stroke:var(--pp-muted);stroke-width:1.8;stroke-linecap:round"/></svg>{{ __('podplan::messages.plan.legend_wireless') }}</span>
    <span><i style="width:20px;border-top:2px dashed var(--pp-axis);"></i>{{ __('podplan::messages.plan.legend_axis') }}</span>
</div>
