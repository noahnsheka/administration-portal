# Timetable Design Guide for Teachers

## Overview
The teacher timetable template has been redesigned based on modern UI/UX best practices for schedule management systems. This guide explains the layout, features, and design decisions.

## Key Features

### 1. **Weekly Grid Layout**
- **Days as Columns**: Monday through Saturday displayed horizontally
- **Time Periods as Rows**: 8:00 AM to 4:15 PM (45-minute slots)
- **Clear Visibility**: Easy scanning for conflicts and gaps in schedule

### 2. **Color-Coded Classes**
- Each class is assigned a unique color from the palette
- Colors persist throughout the interface for consistency
- **Benefits**:
  - Quickly identify different classes at a glance
  - Visual distinction reduces cognitive load
  - Accessible color palette (contrast-friendly)

### 3. **Class Slot Information** (Template Format)
Each timetable cell displays:
```
╔═══════════════════╗
║ Class Name (bold) ║
║ Room: A101        ║
║ 28 students       ║
╚═══════════════════╝
```

### 4. **Interactive Elements**

#### Filter Panel (Left Sidebar)
- **Class Filter Checkboxes**: Toggle visibility of specific classes
- **Quick Actions**:
  - `+ Add Class Slot`: Create new schedule entries
  - `⚙️ Bulk Edit`: Modify multiple slots at once
  - `🖨️ Print Schedule`: Generate printable version

#### Color Legend
- Shows mapping of colors to class names
- Helps teachers remember which color represents which class

#### Navigation Buttons
- **← Previous Week / → Next Week**: Navigate between weeks
- **Current Week**: Jump back to today's schedule

### 5. **Responsive Design**
- **Desktop**: Full grid view with all details
- **Tablet**: Optimized spacing and slightly smaller fonts
- **Mobile**: Scrollable table, stacked legend
- Touches remain accurate and accessible on smaller screens

### 6. **Visual Hierarchy**
- **Header**: Gradient background, clear purpose statement
- **Time Column**: Distinct gray background for quick reference
- **Class Slots**: Elevated cards with subtle shadows
- **Hover Effects**: Slots lift slightly on hover, indicating interactivity

---

## Design Principles Applied

### 1. **Information Scent**
Users can quickly identify what they need:
- Class name immediately visible
- Room location obvious
- Student count apparent
- Time is shown in row headers

### 2. **Visual Consistency**
- Color coding reinforced through legend
- Consistent spacing and padding
- Unified button styles and interactions
- Professional gradient headers

### 3. **Accessibility**
- Sufficient contrast ratios
- Checkbox labels properly associated
- Semantic HTML structure
- Clear focus states for keyboard navigation

### 4. **Cognitive Load Reduction**
- Break times/free periods visually distinct
- No information overload per slot
- Filter options to reduce visual clutter
- Consistent time slot durations

---

## Customization Guide

### Adding Break Times
```php
// Add to time slots array
$timeSlots = [
  '08:00', '08:45', 'BREAK', '09:30', '10:15', 
  '11:00', '11:45', 'LUNCH', '12:30', '13:15', '14:00'
];

// In table rendering, detect breaks:
if ($time === 'BREAK' || $time === 'LUNCH') {
  echo '<td class="break-slot">' . $time . '</td>';
}
```

### Adjusting Time Slots
Modify the `$timeSlots` array to match your school's period schedule:
```php
// Example: 50-minute periods
$timeSlots = [
  '08:00', '08:50', '09:40', '10:30', '11:20', 
  '12:10', '13:00', '13:50', '14:40', '15:30'
];
```

### Changing Color Palette
Update the `$colors` array:
```php
$colors = [
  '#your-color-1', '#your-color-2', '#your-color-3',
  // ... more colors
];
```

### Adding Days
To expand to a full 7-day schedule:
```php
$daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 
               'Friday', 'Saturday', 'Sunday'];
```

---

## Future Enhancement Ideas

### Phase 1: Database Integration
- [ ] Load actual class schedules from database
- [ ] Display actual student counts
- [ ] Show room allocations from timetable data
- [ ] Save and retrieve timetable preferences

### Phase 2: Interactive Editing
- [ ] Click to edit class details
- [ ] Drag-and-drop to reschedule classes
- [ ] Add/remove classes inline
- [ ] Mark substitutions and cancellations

### Phase 3: Advanced Features
- [ ] Week/month/semester views
- [ ] Export to calendar formats (iCal, Google Calendar)
- [ ] SMS/Email notifications for schedule changes
- [ ] Student-facing version with read-only access
- [ ] Conflict detection (double-bookings, room conflicts)

### Phase 4: Analytics & Reporting
- [ ] Utilization reports (busiest periods)
- [ ] Coverage statistics
- [ ] Export schedules to PDF/Excel
- [ ] Schedule comparison tools

---

## Best Practices for Using This Template

### For Teachers
1. **Check Weekly**: Review your schedule each Monday
2. **Update Promptly**: Inform admin of any schedule changes immediately
3. **Plan Lessons**: Use the grid to plan lesson sequences
4. **Note Conflicts**: Flag any room/time conflicts early

### For Administrators
1. **Color Consistency**: Use the same color palette across all interfaces
2. **Validation**: Verify no room double-bookings when creating slots
3. **Student Capacity**: Ensure room sizes match class enrollments
4. **Break Allocation**: Keep breaks consistent across all teachers

---

## Technical Notes

### CSS Classes
- `.timetable-wrapper`: Scrollable container
- `.class-slot`: Individual class cell
- `.break-slot`: Break/lunch period styling
- `.empty-slot`: Available time slot
- `.legend-item`: Color legend entry

### JavaScript Functions
- `previousWeek()`: Navigate to previous week
- `nextWeek()`: Navigate to next week
- `currentWeek()`: Return to current week
- `printTimetable()`: Generate print version

### Database Fields Needed (Future)
When integrating with database:
```sql
CREATE TABLE timetable_entries (
  id INT PRIMARY KEY AUTO_INCREMENT,
  teacher_id INT NOT NULL,
  class_id INT NOT NULL,
  day_of_week VARCHAR(20),
  start_time TIME,
  end_time TIME,
  room_id INT,
  student_count INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (teacher_id) REFERENCES users(id),
  FOREIGN KEY (class_id) REFERENCES classes(id),
  FOREIGN KEY (room_id) REFERENCES rooms(id)
);
```

---

## Browser Compatibility
- ✓ Chrome/Edge 90+
- ✓ Firefox 88+
- ✓ Safari 14+
- ✓ Mobile Safari (iOS 12+)
- ✓ Chrome Mobile

---

## Troubleshooting

### Timetable Not Displaying
1. Check that `getDistinctClassNames()` returns data
2. Verify CSS file is loading (`style.css`)
3. Check browser console for JavaScript errors

### Colors Not Showing
1. Ensure `$colors` array is not empty
2. Verify inline styles are not stripped by security policies
3. Check CSS color format is valid

### Mobile Display Issues
1. Verify viewport meta tag is present
2. Check table width settings for overflow
3. Test on actual mobile device (viewport units vary)

---

## Support & Updates
This template is designed to be expanded with dynamic data from your database. For questions or feature requests, refer to the main README.md documentation.
