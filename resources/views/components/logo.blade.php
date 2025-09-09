@props(['class' => 'h-8 w-8'])

<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
    <!-- Aircraft body -->
    <path d="M50 15 L50 85" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
    
    <!-- Wings -->
    <path d="M25 40 L75 40" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
    <path d="M30 65 L70 65" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
    
    <!-- Nose cone -->
    <circle cx="50" cy="15" r="3" fill="currentColor"/>
    
    <!-- Wing tips -->
    <circle cx="25" cy="40" r="2" fill="currentColor"/>
    <circle cx="75" cy="40" r="2" fill="currentColor"/>
    <circle cx="30" cy="65" r="1.5" fill="currentColor"/>
    <circle cx="70" cy="65" r="1.5" fill="currentColor"/>
    
    <!-- Vertical stabilizer -->
    <path d="M50 75 L45 90 L55 90 Z" fill="currentColor"/>
    
    <!-- Flight path trails -->
    <path d="M20 25 Q30 30 40 25" stroke="currentColor" stroke-width="1.5" opacity="0.6" stroke-linecap="round" fill="none"/>
    <path d="M60 25 Q70 30 80 25" stroke="currentColor" stroke-width="1.5" opacity="0.6" stroke-linecap="round" fill="none"/>
    
    <!-- Speed lines -->
    <path d="M10 35 L20 35" stroke="currentColor" stroke-width="1" opacity="0.4" stroke-linecap="round"/>
    <path d="M10 45 L18 45" stroke="currentColor" stroke-width="1" opacity="0.4" stroke-linecap="round"/>
    <path d="M10 55 L16 55" stroke="currentColor" stroke-width="1" opacity="0.4" stroke-linecap="round"/>
    
    <path d="M80 35 L90 35" stroke="currentColor" stroke-width="1" opacity="0.4" stroke-linecap="round"/>
    <path d="M82 45 L90 45" stroke="currentColor" stroke-width="1" opacity="0.4" stroke-linecap="round"/>
    <path d="M84 55 L90 55" stroke="currentColor" stroke-width="1" opacity="0.4" stroke-linecap="round"/>
</svg>