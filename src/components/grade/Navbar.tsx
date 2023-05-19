import { NavLink, ScrollArea } from '@mantine/core';
import React from 'react';

export default function Navbar({studentSubmissions}: {studentSubmissions: any[]) {
  return (
    <ScrollArea h="90vh">
      {studentSubmissions.map((it) => (
        <NavLink
          key={it.id}
          label={it.userId}
        //   onClick={() => setSelectedItem(it)}
        />
      ))}
    </ScrollArea>
  );
}
