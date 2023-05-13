import { Card, Title } from '@mantine/core';
import { classroom_v1 } from 'googleapis';
import React from 'react';

type Props = {
  item: classroom_v1.Schema$Course;
};
export default function ClassCard({ item }: Props) {
  return (
    <Card>
      <Title>{item.name}</Title>
    </Card>
  );
}
