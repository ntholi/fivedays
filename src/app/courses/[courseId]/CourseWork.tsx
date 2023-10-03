'use client';
import React from 'react';
import { classroom_v1 } from 'googleapis';
import { Accordion, Button, Divider, Flex } from '@mantine/core';
import Link from 'next/link';

type Props = {
  courseWork: classroom_v1.Schema$CourseWork;
};

export default function CourseWork({ courseWork }: Props) {
  return (
    <Accordion.Item value={courseWork.title || ''}>
      <Accordion.Control>{courseWork.title}</Accordion.Control>
      <Accordion.Panel>
        {courseWork.description}
        <Divider mt='xl' mb='md' />
        <Flex justify='start'>
          <Button
            variant='filled'
            color='gray'
            component={Link}
            href={`/course-work/${courseWork.id}`}
          >
            View Course
          </Button>
        </Flex>
      </Accordion.Panel>
    </Accordion.Item>
  );
}
