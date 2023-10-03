'use client';
import React from 'react';
import { classroom_v1 } from 'googleapis';
import { Accordion } from '@mantine/core';

type Props = {
  courseWork: classroom_v1.Schema$CourseWork;
};

export default function CourseWork({ courseWork }: Props) {
  return (
    <Accordion.Item value={courseWork.title || ''}>
      <Accordion.Control>{courseWork.title}</Accordion.Control>
      <Accordion.Panel>{courseWork.description}</Accordion.Panel>
    </Accordion.Item>
  );
}
