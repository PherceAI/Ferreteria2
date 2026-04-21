import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-red-600 text-white font-bold text-lg shadow-sm">
                S
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="truncate text-xs leading-tight font-semibold tracking-[-0.02em]">
                    Comercial San Francisco
                </span>
            </div>
        </>
    );
}
